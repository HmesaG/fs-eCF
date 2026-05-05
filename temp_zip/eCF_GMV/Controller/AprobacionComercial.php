<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiApiService;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiXmlFirmador;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiXmlGenerador;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFLog;

class AprobacionComercial extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Aprobación Comercial (B2B)';
        $pageData['menu'] = 'ecf_gmv';
        $pageData['icon'] = 'fas fa-check-double';
        return $pageData;
    }

    public function getModelClassName(): string
    {
        return ECFLog::class;
    }

    protected function createViews()
    {
        $viewName = 'AprobacionComercial';
        $this->addView($viewName, ECFLog::class, 'e-CF Recibidos', 'fas fa-inbox');
        $this->addOrderBy($viewName, ['fecha'], 'fecha', 2);
    }

    protected function loadData($viewName, $view)
    {
        $where = $this->permissions->onlyOwnerData ? $this->getOwnerFilter($view->model) : [];
        if ($viewName === 'AprobacionComercial') {
            $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('tipo', 'RECEPCION_B2B');
        }
        $view->loadData('', $where);
    }

    public function run(): void
    {
        if ($this->request->query->has('aprobar')) {
            $this->aprobarAction();
        }

        parent::run();
    }

    /**
     * Acción para aprobar un e-CF recibido.
     * Genera el XML ACECF (Aprobación Comercial).
     */
    public function aprobarAction()
    {
        $id = $this->request->query->get('aprobar');
        if (empty($id)) {
            return;
        }

        $log = new ECFLog();
        if (!$log->loadFromCode($id)) {
            $this->error('No se encontró el registro de log.');
            return;
        }

        try {
            $config = (new ECFConfiguracion())->getConfiguracion();
            
            // 1. Parsear el XML recibido para obtener datos
            $dom = new \DOMDocument();
            if (!@$dom->loadXML($log->xml_sin_firma)) {
                throw new \Exception("No se pudo cargar el XML original para procesar la aprobación.");
            }

            $rncEmisor = $dom->getElementsByTagName('RNCEmisor')->item(0)->nodeValue ?? '';
            $encf = $dom->getElementsByTagName('eNCF')->item(0)->nodeValue ?? '';
            $fechaEmision = $dom->getElementsByTagName('FechaEmision')->item(0)->nodeValue ?? date('d-m-Y');
            $montoTotal = $dom->getElementsByTagName('MontoTotal')->item(0)->nodeValue ?? 0;

            // 2. Generar datos para ACECF
            $datosACECF = [
                'rnc_emisor'    => $rncEmisor,
                'encf'          => $encf,
                'fecha_emision' => $fechaEmision,
                'monto_total'   => (float)$montoTotal,
                'rnc_comprador' => $config->rnc_emisor,
                'estado'        => 1, // 1 = Aprobación Total
            ];

            $xmlACECF = DgiiXmlGenerador::generarACECF($datosACECF);
            
            // 3. Firmar el ACECF
            $xmlFirmado = DgiiXmlFirmador::firmarECF(
                $xmlACECF,
                $config->ruta_certificado_p12,
                $config->password_certificado
            );

            // 4. Obtener URL de recepción del emisor
            $api = new DgiiApiService();
            $urlDestino = $api->consultarDirectorio($rncEmisor);
            
            if (empty($urlDestino)) {
                // Si no se encuentra en el directorio, intentamos buscar en el cliente si existe
                $urlDestino = $this->buscarUrlEnCliente($rncEmisor);
            }

            if (empty($urlDestino)) {
                $this->warning("No se pudo encontrar el endpoint de recepción B2B para el RNC $rncEmisor. El ACECF se ha generado pero no se ha enviado.");
            } else {
                // 5. Enviar el ACECF firmado
                $resEnvio = $api->enviarACECF($urlDestino, $xmlFirmado, "ACECF-$encf", $xmlACECF);
                if ($resEnvio['estado'] === 1) {
                    $this->success("Se ha enviado la Aprobación Comercial (ACECF) al emisor $rncEmisor.");
                } else {
                    $this->error("Error enviando ACECF: " . $resEnvio['mensaje']);
                }
            }
        } catch (\Exception $e) {
            $this->error("Error al procesar aprobación: " . $e->getMessage());
        }
    }

    private function buscarUrlEnCliente(string $rnc): ?string
    {
        $cliente = new \FacturaScripts\Core\Model\Cliente();
        if ($cliente->loadFromCode($rnc, 'cifnif')) {
            return $cliente->url_recepcion_ecf ?? null;
        }

        return null;
    }
}
