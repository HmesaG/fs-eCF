<?php

namespace FacturaScripts\Plugins\eCF_GMV\Extension\Model;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;

class Cliente
{
    public function clear(): \Closure
    {
        return function () {
            $this->url_recepcion_ecf = '';
        };
    }
}
