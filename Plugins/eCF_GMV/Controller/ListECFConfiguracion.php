<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * Listado de registros de configuración e-CF.
 * Al haber solo un registro, el usuario entra directamente al Edit al hacer click.
 */
class ListECFConfiguracion extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Configuración e-CF';
        $pageData['menu'] = 'ecf_gmv';
        $pageData['icon'] = 'fas fa-cog';
        return $pageData;
    }

    public function getModelClassName(): string
    {
        return 'ECFConfiguracion';
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        // Al haber solo un registro de configuración, redirigimos directamente al Edit
        $this->redirect('EditECFConfiguracion?code=1');
        return;
    }

    protected function createViews()
    {
        $this->addView('ListECFConfiguracion', 'ECFConfiguracion', 'Configuración e-CF', 'fas fa-cog');
        $this->setSettings('ListECFConfiguracion', 'btnNew', false);
    }
}
