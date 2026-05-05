<?php
/**
 * Extensión del controlador EditFacturaCliente.
 * Agrega la acción "enviar-ecf" para enviar la factura a DGII.
 */

namespace FacturaScripts\Plugins\eCF_GMV\Extension\Controller;

use Closure;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\eCF_GMV\Lib\EmisorECF;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiApiService;

class EditFacturaCliente
{
    public function createViews(): Closure
    {
        return function () {
            /** @var \FacturaScripts\Core\Lib\ExtendedController\EditController $this */
            $this->addButton('EditFacturaCliente', [
                'action' => 'imprimir-ecf',
                'icon'   => 'fas fa-qrcode',
                'label'  => 'Imprimir e-CF QR',
                'color'  => 'info',
            ]);

            $this->addButton('EditFacturaCliente', [
                'action' => 'enviar-ecf',
                'icon'   => 'fas fa-paper-plane',
                'label'  => 'Enviar e-CF',
                'color'  => 'primary',
            ]);
        };
    }

    public function execPreviousAction(): Closure
    {
        return function (string $action): bool {
            /** @var \FacturaScripts\Core\Lib\ExtendedController\EditController $this */
            if ($action === 'imprimir-ecf') {
                $idfactura = $this->request->get('code', '');
                if (!empty($idfactura)) {
                    $this->redirect('ImprimirECF?idfactura=' . $idfactura);
                }
                return false; // Detener ejecución del kernel tras interceptar la acción
            }
            return true; // Permitir flujo normal para otras acciones
        };
    }

    public function execAfterAction(): Closure
    {
        return function (string $action) {
            /** @var \FacturaScripts\Core\Lib\ExtendedController\EditController $this */
            if ($action !== 'enviar-ecf') {
                return;
            }

            $idfactura = $this->request->get('code', '');
            if (empty($idfactura)) {
                Tools::log()->error('ecf-no-factura');
                return;
            }

            $factura = new FacturaCliente();
            if (!$factura->loadFromCode($idfactura)) {
                Tools::log()->error('ecf-factura-no-encontrada', ['%code%' => $idfactura]);
                return;
            }

            try {
                $emisor    = new EmisorECF();
                $resultado = $emisor->emitir($factura);

                if ($resultado['ok']) {
                    $estadoTexto = DgiiApiService::getStatusText((int)$resultado['estado']);
                    Tools::log()->notice('ecf-enviada-ok', [
                        '%encf%'   => $factura->numeroncf,
                        '%estado%' => $estadoTexto,
                    ]);
                } else {
                    Tools::log()->error('ecf-error-envio', ['%mensaje%' => $resultado['mensaje']]);
                }

            } catch (\RuntimeException $e) {
                Tools::log()->error($e->getMessage());
            }
        };
    }
}
