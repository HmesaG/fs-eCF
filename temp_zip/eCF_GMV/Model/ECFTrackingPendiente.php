<?php
namespace FacturaScripts\Plugins\eCF_GMV\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

class ECFTrackingPendiente extends ModelClass
{
    use ModelTrait;

    public $id;
    public $encf;
    public $rnc_emisor;
    public $trackid;
    public $referencia_local;
    public $tipo_documento;
    public $estado;
    public $ultimo_mensaje;
    public $intentos;
    public $fecha_envio;
    public $fecha_ultima_consulta;
    public $resuelto;

    public static function primaryColumn(): string { return 'id'; }
    public static function tableName(): string { return 'ecf_tracking_pendiente'; }

    public function clear(): void
    {
        parent::clear();
        $this->id                    = null;
        $this->encf                  = '';
        $this->rnc_emisor            = '';
        $this->trackid               = '';
        $this->referencia_local      = '';
        $this->tipo_documento        = 'FacturaCliente';
        $this->estado                = '3';
        $this->ultimo_mensaje        = '';
        $this->intentos              = 0;
        $this->fecha_envio           = date('Y-m-d H:i:s');
        $this->fecha_ultima_consulta = date('Y-m-d H:i:s');
        $this->resuelto              = false;
    }

    public static function getPendientes(): array
    {
        return (new self())->all(
            [new DataBaseWhere('resuelto', false)],
            ['fecha_envio' => 'ASC'],
            0,
            200
        );
    }
}
