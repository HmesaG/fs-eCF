<?php

namespace FacturaScripts\Plugins\eCF_GMV\Lib\DGII;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;

class DgiiResumenServicio
{
    /**
     * Genera el Reporte de Consumo Electrónico (RFCE) para un día específico.
     * Agrupa todas las facturas E32 menores a 250,000 DOP.
     */
    public static function procesarDia(\DateTime $fecha): array
    {
        $db = new DataBase();
        $fStr = $fecha->format('Y-m-d');
        
        // Buscamos facturas E32 (Consumidor Final) de ese día que no hayan sido enviadas individualmente
        // En FacturaScripts, el NCF suele estar en la columna 'ncf'
        $sql = "SELECT * FROM facturascli WHERE fecha = ? AND ncf LIKE 'E32%' AND total < 250000";
        $facturas = $db->select($sql, [$fStr]);

        if (empty($facturas)) {
            return ['status' => 'info', 'mensaje' => 'No hay facturas E32 para procesar en esta fecha.'];
        }

        $config = (new ECFConfiguracion())->getConfiguracion();
        
        // Consolidación de totales
        $totalNeto = 0;
        $totalItbis = 0;
        foreach ($facturas as $f) {
            $totalNeto += (float)$f['total'];
            $totalItbis += (float)$f['totaliva'];
        }

        $datosRFCE = [
            'encf'           => 'E32' . str_replace('-', '', $fStr) . '001', // Ejemplo de secuencial diario
            'emisor_rnc'     => $config->rnc_emisor,
            'emisor_nombre'  => $config->razon_social,
            'fecha_emision'  => $fStr,
            'total_neto'     => $totalNeto,
            'total_itbis'    => $totalItbis,
            'tipo_ingreso'   => '01', // Ingresos por operaciones ordinarias
            'tipo_pago'      => '1',  // Efectivo (promedio o predominante)
        ];

        try {
            $xmlSinFirma = DgiiXmlGenerador::generarRFCE($datosRFCE);
            $xmlFirmado = DgiiXmlFirmador::firmarECF(
                $xmlSinFirma,
                $config->ruta_certificado_p12,
                $config->password_certificado
            );

            $api = new DgiiApiService();
            $res = $api->enviarRFCE($xmlFirmado, "RFCE-$fStr", $xmlSinFirma);

            return [
                'status'  => $res['estado'] === 1 ? 'success' : 'error',
                'mensaje' => $res['mensaje'],
                'xml'     => $xmlFirmado
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'mensaje' => $e->getMessage()];
        }
    }
}
