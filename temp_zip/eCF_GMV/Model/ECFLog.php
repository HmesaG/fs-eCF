<?php
namespace FacturaScripts\Plugins\eCF_GMV\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

class ECFLog extends ModelClass
{
    use ModelTrait;

    public $id;
    public $tipo;
    public $referencia;
    public $peticion;
    public $respuesta;
    public $http_code;
    public $xml_sin_firma;
    public $xml_firmado;
    public $fecha;

    public static function primaryColumn(): string { return 'id'; }
    public static function tableName(): string { return 'ecf_log'; }

    public function clear(): void
    {
        parent::clear();
        $this->id         = null;
        $this->tipo       = '';
        $this->referencia = '';
        $this->peticion   = '';
        $this->respuesta  = '';
        $this->http_code  = 0;
        $this->xml_sin_firma = '';
        $this->xml_firmado   = '';
        $this->fecha         = date('Y-m-d H:i:s');
    }
}
