<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Listado de trackings pendientes de confirmación ante DGII.
 */
class ListECFTrackingPendiente extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Tracking Pendiente e-CF';
        $pageData['menu'] = 'ecf_gmv';
        $pageData['icon'] = 'fas fa-clock';
        return $pageData;
    }

    public function getModelClassName(): string
    {
        return 'ECFTrackingPendiente';
    }

    protected function createViews()
    {
        $this->addView('ListECFTrackingPendiente', \FacturaScripts\Plugins\eCF_GMV\Model\ECFTrackingPendiente::class, 'Trackings Pendientes', 'fas fa-clock');
        $this->addSearchFields('ListECFTrackingPendiente', ['encf', 'trackid', 'estado', 'referencia_local']);
        $this->addOrderBy('ListECFTrackingPendiente', ['fecha_envio'], 'fecha-envio', 2);
        $this->addOrderBy('ListECFTrackingPendiente', ['intentos'], 'intentos');
        $this->addFilterCheckbox('ListECFTrackingPendiente', 'resuelto', 'resuelto');
    }
}
