<?php

namespace FacturaScripts\Plugins\eCF_GMV\Extension\Model;

/**
 * Extensión del modelo Cliente para añadir campos eCF-GMV.
 *
 * NOTA SOBRE ENCADENAMIENTO:
 * El framework llama a todas las closures registradas para 'clear' automáticamente
 * mediante $this->pipe('clear') en ModelCore::clear(). No se necesita parentClear().
 * Cada closure solo debe limpiar los campos que le pertenecen.
 */
class Cliente
{
}
