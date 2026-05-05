<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Controlador de edición de registros del log de envíos e-CF.
 * Los logs son de solo lectura; se visualizan pero no se editan manualmente.
 */
class EditECFLog extends EditController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Envío e-CF';
        $pageData['menu'] = 'ecf_gmv';
        $pageData['icon'] = 'fas fa-history';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function getModelClassName(): string
    {
        return \FacturaScripts\Plugins\eCF_GMV\Model\ECFLog::class;
    }

    protected function createViews()
    {
        $this->addEditView('EditECFLog', \FacturaScripts\Plugins\eCF_GMV\Model\ECFLog::class, 'Detalle del Envío', 'fas fa-history');
    }
}
