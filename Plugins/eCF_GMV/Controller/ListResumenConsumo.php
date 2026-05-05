<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiResumenServicio;

class ListResumenConsumo extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'Resumen de Consumo (RFCE)';
        $data['menu'] = 'ecf_gmv';
        $data['icon'] = 'fas fa-file-invoice-dollar';
        return $data;
    }

    protected function createViews()
    {
        $this->addView('ListResumenConsumo', 'ResumenConsumo');

        // Añadimos el botón de acción para procesar el día seleccionado
        $this->addButton('ListResumenConsumo', [
            'action' => 'procesar',
            'label' => 'Procesar y Enviar',
            'icon' => 'fas fa-paper-plane',
            'color' => 'primary',
        ]);
    }

    /**
     * En este controlador no listamos una tabla directamente desde la DB,
     * sino que usamos el servicio para obtener los días pendientes.
     */
    protected function loadData($viewName, $view)
    {
        if ($viewName !== 'ListResumenConsumo') {
            return;
        }

        $datos = DgiiResumenServicio::getPendientes();

        $view->setItems([], 0);

        foreach ($datos as $item) {
            $nuevoModelo = clone $view->model;
            foreach ($item as $campo => $valor) {
                if (property_exists($nuevoModelo, $campo)) {
                    $nuevoModelo->$campo = $valor;
                }
            }
            $view->addItem($nuevoModelo);
        }

        $view->count = count($datos);
    }

    protected function execPreviousAction($action): bool
    {
        $fechaStr = $this->request->get('code') ?? $this->request->get('fecha');

        if ($action === 'procesar' && !empty($fechaStr)) {
            try {
                $fecha = new \DateTime($fechaStr);
                $res = DgiiResumenServicio::procesarDia($fecha);

                if ($res['status'] === 'success') {
                    Tools::log()->info($res['mensaje']);
                } elseif ($res['status'] === 'info') {
                    Tools::log()->warning($res['mensaje']);
                } else {
                    Tools::log()->error($res['mensaje']);
                }
            } catch (\Exception $e) {
                Tools::log()->error('Error: ' . $e->getMessage());
            }
            return false;
        }

        return parent::execPreviousAction($action);
    }
}
