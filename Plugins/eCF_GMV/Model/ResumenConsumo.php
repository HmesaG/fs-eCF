<?php

namespace FacturaScripts\Plugins\eCF_GMV\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

/**
 * Modelo ficticio requerido por ListResumenConsumo para satisfacer
 * el contrato de addView() en ListController.
 *
 * Este modelo no mapea a ninguna tabla real de la base de datos.
 * Los datos reales los inyecta el servicio DgiiResumenServicio::getPendientes()
 * directamente en $this->views[$viewName]->data dentro de loadData().
 *
 * El tableName vacío previene que FacturaScripts intente crear o migrar
 * una tabla en la base de datos para este modelo.
 */
class ResumenConsumo extends ModelClass
{
    use ModelTrait;

    /** @var string Fecha del día a resumir */
    public $fecha;

    /** @var float Total del resumen */
    public $total;

    /** @var int Cantidad de e-CF enviados ese día */
    public $cantidad;

    /** @var string Estado DGII del resumen ('PENDIENTE', 'ENVIADO', 'ERROR') */
    public $estado;

    /** @var string Mensaje adicional del servicio RFCE */
    public $mensaje;

    public static function primaryColumn(): string
    {
        return 'fecha';
    }

    public static function tableName(): string
    {
        return 'tmp_resumen_consumo';
    }

    public function install(): string
    {
        // Sin DDL: no se crea ninguna tabla en la base de datos
        return '';
    }

    public function test(): bool
    {
        return true;
    }
}
