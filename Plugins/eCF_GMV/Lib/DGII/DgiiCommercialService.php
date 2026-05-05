<?php

namespace FacturaScripts\Plugins\eCF_GMV\Lib\DGII;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFLog;
use FacturaScripts\Core\Tools;

class DgiiCommercialService
{
    /**
     * Procesa la aprobación comercial de una factura recibida.
     * 
     * @param ECFLog $log
     * @param int $estado 0=Aprobado, 1=Aprobado Parcial, 2=Rechazado
     * @param string $motivo
     * @return bool
     */
    public static function enviarAprobacion(ECFLog $log, int $estado, string $motivo = ''): bool
    {
        try {
            $config = (new ECFConfiguracion())->getConfiguracion();
            if (!$config) {
                throw new \Exception("Plugin eCF-GMV no configurado. Ingrese la configuración del certificado.");
            }
            if (!$config->activo) {
                throw new \Exception("El plugin eCF no está activado.");
            }

            // 1. Parsear el XML original para obtener datos
            $datosOriginal = DgiiXmlParser::parse($log->xml_sin_firma);

            // 2. Preparar datos para ACECF
            $datosACECF = [
                'rnc_emisor'    => $datosOriginal['rnc_emisor'],
                'encf'          => $datosOriginal['encf'],
                'fecha_emision' => $datosOriginal['fecha_emision'],
                'monto_total'   => $datosOriginal['monto_total'],
                'rnc_comprador' => $config->rnc_emisor,
                'estado'        => $estado,
                'motivo_rechazo' => $motivo
            ];

            // 3. Generar XML
            $xmlACECF = DgiiXmlGenerador::generarACECF($datosACECF);

            // 4. Firmar
            $xmlFirmado = DgiiXmlFirmador::firmarECF(
                $xmlACECF,
                $config->ruta_certificado_p12,
                $config->password_certificado
            );

            // 5. Intentar envío al emisor (B2B)
            $api = new DgiiApiService();
            $urlDestino = $api->consultarDirectorio($datosOriginal['rnc_emisor']);
            
            if (empty($urlDestino)) {
                // Si no está en el directorio, buscamos en la ficha del cliente (si existe)
                $cliente = new \FacturaScripts\Dinamic\Model\Cliente();
                $clientes = $cliente->all([['cifnif', '=', $datosOriginal['rnc_emisor']]], [], 1);
                if (!empty($clientes)) {
                    $urlDestino = $clientes[0]->url_recepcion_ecf ?? null;
                }
            }

            if (empty($urlDestino)) {
                Tools::log()->warning("B2B: No se encontró URL de recepción para RNC " . $datosOriginal['rnc_emisor'] . ". Se registra el ACECF pero no se pudo enviar.");
                $resEnvio = ['estado' => 0, 'mensaje' => 'URL de destino no encontrada'];
            } else {
                $resEnvio = $api->enviarACECF($urlDestino, $xmlFirmado, "ACECF-" . $datosOriginal['encf'], $xmlACECF);
            }

            // 6. Actualizar el log original con el estado del trámite
            if ($resEnvio['estado'] === 1) {
                $log->respuesta = ($estado === 0 ? 'APROBADA' : 'RECHAZADA');
                $log->save();
            } else {
                $log->respuesta = 'ERROR: ' . $resEnvio['mensaje'];
                $log->save();
            }

            return $resEnvio['estado'] === 1;

        } catch (\Exception $e) {
            Tools::log()->error("Error en Aprobación Comercial: " . $e->getMessage());
            return false;
        }
    }
}
