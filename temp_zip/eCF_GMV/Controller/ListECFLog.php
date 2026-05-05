<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Listado del log de envíos e-CF a DGII.
 */
class ListECFLog extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Envíos e-CF';
        $pageData['menu'] = 'ecf_gmv';
        $pageData['icon'] = 'fas fa-history';
        return $pageData;
    }

    public function getModelClassName(): string
    {
        return 'ECFLog';
    }

    protected function createViews()
    {
        $this->addView('ListECFLog', \FacturaScripts\Plugins\eCF_GMV\Model\ECFLog::class, 'Registro de Envíos', 'fas fa-history');
        $this->addSearchFields('ListECFLog', ['referencia', 'tipo']);
        $this->addOrderBy('ListECFLog', ['fecha'], 'fecha', 2);
        $this->addOrderBy('ListECFLog', ['http_code'], 'http-code');
    }
}
