<?php

namespace FacturaScripts\Plugins\eCF_GMV\Lib\DGII;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFLog;
use FacturaScripts\Plugins\eCF_GMV\Lib\EmisorECF;

class DgiiResumenServicio
{
    /**
     * Genera y envía el Reporte de Consumo Electrónico (RFCE) para un día específico.
     * Agrupa todas las facturas E32 menores a 250,000 DOP.
     */
    public static function procesarDia(\DateTime $fecha): array
    {
        $db = new DataBase();
        $fStr = $fecha->format('Y-m-d');
        
        // 1. Verificar si ya se envió un RFCE para este día (exitosamente)
        $logModel = new ECFLog();
        $sqlCheck = "SELECT id FROM " . $logModel->tableName() . " WHERE tipo = 'ENVIO_RFCE' AND referencia LIKE ? AND http_code IN (200, 201)";
        $yaEnviado = $db->select($sqlCheck, ["%RFCE-$fStr%"]);
        if (!empty($yaEnviado)) {
            return ['status' => 'info', 'mensaje' => "El reporte de consumo para el día $fStr ya fue enviado previamente."];
        }

        // 2. Buscar facturas E32 (Consumidor Final) de ese día < UMBRAL_RFCE
        $sql = "SELECT total, totaliva FROM facturascli WHERE fecha = ? AND numeroncf LIKE 'E32%' AND total < " . EmisorECF::UMBRAL_RFCE;
        $facturas = $db->select($sql, [$fStr]);

        if (empty($facturas)) {
            return ['status' => 'info', 'mensaje' => "No hay facturas E32 menores a 250k para procesar en la fecha $fStr."];
        }

        $config = (new ECFConfiguracion())->getConfiguracion();
        if (!$config) {
            return ['status' => 'error', 'mensaje' => 'Configuración eCF no encontrada.'];
        }
        
        // 3. Consolidación de totales
        $totalMonto = 0;
        $totalItbis = 0;
        foreach ($facturas as $f) {
            $totalMonto += (float)$f['total'];
            $totalItbis += (float)$f['totaliva'];
        }

        // Generar un secuencial interno para el RFCE (basado en la fecha)
        $secuencial = str_replace('-', '', $fStr);

        $datosRFCE = [
            'encf'           => 'E32' . $secuencial . '99', // Usamos un sufijo distintivo para el reporte
            'emisor_rnc'     => $config->rnc_emisor,
            'emisor_nombre'  => $config->razon_social,
            'fecha_emision'  => $fStr,
            'total_neto'     => $totalMonto,
            'total_itbis'    => $totalItbis,
            'tipo_ingreso'   => '01', // Ingresos por operaciones ordinarias
            'tipo_pago'      => '1',  // Efectivo (predominante en consumidor final)
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
            return ['status' => 'error', 'mensaje' => 'Error procesando RFCE: ' . $e->getMessage()];
        }
    }

    /**
     * Retorna una lista de días con facturas E32 que no han sido reportadas.
     */
    public static function getPendientes(): array
    {
        $db = new DataBase();
        // Buscamos fechas de facturas E32 < UMBRAL_RFCE
        $sql = "SELECT fecha, COUNT(*) as cantidad, SUM(total) as total 
                FROM facturascli 
                WHERE numeroncf LIKE 'E32%' AND total < " . EmisorECF::UMBRAL_RFCE . " 
                GROUP BY fecha 
                ORDER BY fecha DESC 
                LIMIT 30";
        $fechas = $db->select($sql);
        
        $pendientes = [];
        foreach ($fechas as $f) {
            $fStr = $f['fecha'];
            $logModel = new ECFLog();
            $sqlCheck = "SELECT id FROM " . $logModel->tableName() . " WHERE tipo = 'ENVIO_RFCE' AND referencia LIKE ? AND http_code IN (200, 201)";
            $yaEnviado = $db->select($sqlCheck, ["%RFCE-$fStr%"]);
            
            if (empty($yaEnviado)) {
                $pendientes[] = $f;
            }
        }
        
        return $pendientes;
    }
}
