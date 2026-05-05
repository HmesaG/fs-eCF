<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiXmlFirmador;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiXmlGenerador;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiXmlParser;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiPurchaseService;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFLog;

/**
 * Endpoint para recibir e-CF de otros contribuyentes (B2B).
 * La URL será: /index.php?page=ApiRecepcion
 */
class ApiRecepcion extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'Recepción e-CF';
        $data['menu'] = '';
        $data['showonmenu'] = false;
        return $data;
    }

    public function run(): void
    {
        $this->setTemplate(false);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(405, ['mensaje' => 'Método no permitido. Use POST.']);
            return;
        }

        $xmlRecibido = file_get_contents('php://input');
        if (empty($xmlRecibido)) {
            $this->jsonResponse(400, ['mensaje' => 'Payload vacío.']);
            return;
        }

        try {
            $config = (new ECFConfiguracion())->getConfiguracion();
            
            // 1. Cargar XML para validación básica
            $dom = new \DOMDocument();
            if (!@$dom->loadXML($xmlRecibido)) {
                throw new \Exception("XML mal formado.");
            }

            // 2. Extraer datos para el log y el acuse
            $rncEmisor = $dom->getElementsByTagName('RNCEmisor')->item(0)->nodeValue ?? '000000000';
            $encf = $dom->getElementsByTagName('eNCF')->item(0)->nodeValue ?? 'Desconocido';

            // 3. Registrar en auditoría
            $logId = $this->registrarLog($xmlRecibido, $rncEmisor, $encf);

            // NUEVO: Automatización de Compras
            try {
                $datosXml = DgiiXmlParser::parse($xmlRecibido);
                $idFacturaProv = DgiiPurchaseService::createPurchase($datosXml);
                if ($idFacturaProv) {
                    $log = new ECFLog();
                    if ($log->loadFromCode($logId)) {
                        $log->idfacturaproveedor = $idFacturaProv;
                        $log->save();
                    }
                }
            } catch (\Exception $ex) {
                \FacturaScripts\Core\Tools::log()->warning("B2B: No se pudo procesar la compra automática: " . $ex->getMessage());
            }

            // 4. Generar ARECF (Acuse de Recibo Electrónico)
            // Según Norma 06-2018, el comprador debe devolver un ARECF
            $datosARECF = [
                'rnc_emisor'    => $rncEmisor,
                'rnc_comprador' => $config->rnc_emisor ?? 'TU_RNC_AQUI',
                'encf'          => $encf,
                'estado'        => 0, // 0 = Recibido conforme
                'fecha'         => date('d-m-Y H:i:s')
            ];

            $xmlARECF = DgiiXmlGenerador::generarARECF($datosARECF);
            
            // 5. Firmar el ARECF
            $xmlFirmado = DgiiXmlFirmador::firmarECF(
                $xmlARECF,
                $config->ruta_certificado_p12,
                $config->password_certificado
            );

            // 6. Retornar ARECF firmado
            header('Content-Type: application/xml; charset=utf-8');
            echo $xmlFirmado;
            exit;

        } catch (\Exception $e) {
            $this->registrarLog($xmlRecibido, 'ERROR', 'ERROR', $e->getMessage());
            $this->jsonResponse(500, ['mensaje' => $e->getMessage()]);
        }
    }

    private function registrarLog(string $xml, string $rnc, string $encf, string $error = ''): int
    {
        $log = new ECFLog();
        $log->tipo = 'RECEPCION_B2B';
        $log->referencia = "$rnc | $encf";
        $log->xml_sin_firma = $xml;
        $log->respuesta = $error;
        $log->fecha = date('Y-m-d H:i:s');
        if ($log->save()) {
            return (int)$log->id;
        }
        return 0;
    }

    private function jsonResponse(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
