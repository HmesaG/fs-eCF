<?php
/**
 * Cron del plugin eCF-GMV.
 * Re-consulta cada 15 min las facturas con estado DGII "En Proceso" (estado 3).
 */

namespace FacturaScripts\Plugins\eCF_GMV;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiApiService;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFTrackingPendiente;

class Cron extends CronClass
{
    public function run(): void
    {
        $config = (new ECFConfiguracion())->getConfiguracion();
        if (!$config || !$config->activo) {
            return;
        }

        if ($this->isTimeForJob('ecf-gmv-consulta-pendientes', '15 minutes')) {
            $this->consultarPendientes($config);
            $this->jobDone('ecf-gmv-consulta-pendientes');
        }

        // Procesar RFCE diariamente a las 23:50
        if ($this->isTimeForJob('ecf-gmv-rfce-diario', '1 day')) {
            // Solo procesamos si es cerca de medianoche o al inicio del día siguiente
            $this->procesarRFCE();
            $this->jobDone('ecf-gmv-rfce-diario');
        }
    }

    private function procesarRFCE(): void
    {
        $ayer = new \DateTime();
        $ayer->modify('-1 day');
        \FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiResumenServicio::procesarDia($ayer);
    }

    private function consultarPendientes(ECFConfiguracion $config): void
    {
        $pendientes = ECFTrackingPendiente::getPendientes();
        if (empty($pendientes)) {
            return;
        }

        try {
            $api = new DgiiApiService();
        } catch (\RuntimeException $e) {
            Tools::log()->warning('eCF-GMV Cron: ' . $e->getMessage());
            return;
        }

        foreach ($pendientes as $pendiente) {
            try {
                $resultado = $api->consultarEstado(
                    $pendiente->rnc_emisor,
                    $pendiente->encf,
                    $pendiente->trackid
                );

                $pendiente->estado                = (string)$resultado['estado'];
                $pendiente->ultimo_mensaje        = $resultado['mensaje'];
                $pendiente->intentos              += 1;
                $pendiente->fecha_ultima_consulta  = date('Y-m-d H:i:s');

                // Estados finales: 1=Aceptado, 2=Rechazado, 4=Aceptado Condicional
                if (in_array((int)$pendiente->estado, [1, 2, 4], true)) {
                    $pendiente->resuelto = true;
                    $this->actualizarFactura($pendiente, (int)$resultado['estado']);
                }

                // Superar el máximo de reintentos
                if ($pendiente->intentos >= ($config->reintentos_maximos * 10)) {
                    $pendiente->resuelto = true;
                }

                $pendiente->save();

            } catch (\Exception $e) {
                Tools::log()->error('eCF-GMV Cron error en ' . $pendiente->encf . ': ' . $e->getMessage());
            }
        }
    }

    private function actualizarFactura(ECFTrackingPendiente $pendiente, int $estado): void
    {
        $factura = $pendiente->tipo_documento === 'FacturaCliente'
            ? new FacturaCliente()
            : new FacturaProveedor();

        if (!$factura->loadFromCode($pendiente->referencia_local)) {
            Tools::log()->warning('eCF-GMV Cron: No se encontró ' . $pendiente->tipo_documento . ' ' . $pendiente->referencia_local);
            return;
        }

        $factura->ecf_estado_dgii = $estado;
        $factura->save();
    }
}
