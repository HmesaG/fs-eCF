<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controlador de edición de registros de tracking pendiente e-CF.
 */
class EditECFTrackingPendiente extends EditController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Tracking e-CF';
        $pageData['menu'] = 'ecf_gmv';
        $pageData['icon'] = 'fas fa-clock';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function getModelClassName(): string
    {
        return \FacturaScripts\Plugins\eCF_GMV\Model\ECFTrackingPendiente::class;
    }

    protected function createViews()
    {
        $this->addEditView('EditECFTrackingPendiente', \FacturaScripts\Plugins\eCF_GMV\Model\ECFTrackingPendiente::class, 'Estado del Tracking', 'fas fa-clock');
    }
}
