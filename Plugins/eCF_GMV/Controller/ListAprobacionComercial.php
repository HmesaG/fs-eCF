<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiCommercialService;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFLog;

class ListAprobacionComercial extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'Aprobación Comercial B2B';
        $data['menu'] = 'ecf_gmv';
        $data['icon'] = 'fas fa-check-double';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewECFLog();
    }

    protected function createViewECFLog(string $viewName = 'ListAprobacionComercial')
    {
        $this->addView($viewName, 'ECFLog', 'Facturas Recibidas');
        
        // Filtramos solo las recepciones B2B
        $this->addOrderBy($viewName, ['fecha'], 'fecha', 2);
        
        // Acciones
        $this->addButton($viewName, [
            'action' => 'aprobar',
            'label'  => 'Aprobar',
            'icon'   => 'fas fa-check',
            'color'  => 'success'
        ]);

        $this->addButton($viewName, [
            'action' => 'rechazar',
            'label'  => 'Rechazar',
            'icon'   => 'fas fa-times',
            'color'  => 'danger'
        ]);
    }

    protected function execPreviousAction($action): bool
    {
        $id = $this->request->get('id');

        if ($id && in_array($action, ['aprobar', 'rechazar'], true)) {
            $log = new ECFLog();
            if ($log->loadFromCode($id)) {
                $this->processActionECF($action, $log);
            }
            return false; // Evita que el core ejecute su propia lógica para esta acción
        }

        return parent::execPreviousAction($action);
    }

    protected function processActionECF(string $action, ECFLog $log): void
    {
        switch ($action) {
            case 'aprobar':
                if (DgiiCommercialService::enviarAprobacion($log, 0)) {
                    $this->success('Factura aprobada comercialmente.');
                } else {
                    $this->error('Error al procesar la aprobación.');
                }
                break;

            case 'rechazar':
                $motivo = $this->request->get('motivo', 'Rechazo comercial por discrepancia en mercancía/precio.');
                if (DgiiCommercialService::enviarAprobacion($log, 2, $motivo)) {
                    $this->success('Factura rechazada comercialmente.');
                } else {
                    $this->error('Error al procesar el rechazo.');
                }
                break;
        }
    }

    protected function loadData($viewName, $view = null)
    {
        // Forzamos el filtro para solo ver recepciones B2B
        if ($viewName === 'ListAprobacionComercial') {
            $this->views[$viewName]->where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('tipo', 'RECEPCION_B2B');
        }
        parent::loadData($viewName);
    }
}
