<?php
/**
 * Plugin eCF-GMV — Controlador para imprimir el PDF del e-CF.
 * Ruta: /ImprimirECF?idfactura=X
 */

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\eCF_GMV\Lib\PDF\ECFPdfGenerator;

/**
 * Controlador que genera y descarga el PDF del Comprobante Fiscal Electrónico.
 *
 * @author eCF-GMV Plugin
 */
class ImprimirECF extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'Imprimir e-CF';
        $data['menu'] = 'ecf_gmv';
        $data['icon'] = 'fas fa-file-pdf';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        $idfactura = (int)$this->request->get('idfactura', 0);
        if ($idfactura <= 0) {
            \FacturaScripts\Core\Tools::log()->warning('Factura no especificada.');
            $this->redirect('ListFacturaCliente');
            return;
        }

        $factura = new FacturaCliente();
        if (false === $factura->loadFromCode($idfactura)) {
            \FacturaScripts\Core\Tools::log()->warning('Factura no encontrada: ' . $idfactura);
            $this->redirect('ListFacturaCliente');
            return;
        }

        // Verificar que tiene NCF (código e-CF)
        if (empty($factura->codigo)) {
            \FacturaScripts\Core\Tools::log()->warning('Esta factura no tiene NCF asignado.');
            $this->redirect('EditFacturaCliente?code=' . $idfactura);
            return;
        }

        try {
            $generator = new ECFPdfGenerator($factura);
            $pdfContent = $generator->generate();

            $filename = 'eCF_' . $factura->codigo . '_' . date('Ymd') . '.pdf';

            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');
            $response->headers->set('Content-Length', strlen($pdfContent));
            $response->setContent($pdfContent);

        } catch (\Exception $e) {
            \FacturaScripts\Core\Tools::log()->error('Error al generar el PDF del comprobante: ' . $e->getMessage());
            $this->redirect('EditFacturaCliente?code=' . $idfactura);
        }
    }
}
