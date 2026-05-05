<?php

namespace FacturaScripts\Plugins\eCF_GMV\Extension\Model;

use FacturaScripts\Core\Base\DataBase;

/**
 * Extensión del modelo FacturaCliente para soportar campos e-CF.
 */
class FacturaCliente
{
    public function test(): \Closure
    {
        return function() {
            // Podemos añadir validaciones aquí si es necesario
        };
    }
}
