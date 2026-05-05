<?php
namespace FacturaScripts\Plugins\eCF_GMV\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

class ECFConfiguracion extends ModelClass
{
    use ModelTrait;

    public $id;
    public $ambiente;
    public $rnc_emisor;
    public $razon_social;
    public $url_base_testecf;
    public $url_base_ecf;
    public $url_rfce_test;
    public $url_rfce_prod;
    public $ruta_certificado_p12;
    public $password_certificado;
    public $timeout_segundos;
    public $reintentos_maximos;
    public $activo;
    public $cert_sujeto;
    public $cert_emisor;
    public $cert_vencimiento;

    public static function primaryColumn(): string { return 'id'; }
    public static function tableName(): string { return 'ecf_configuracion'; }

    public function clear(): void
    {
        parent::clear();
        $this->id                    = null;
        $this->ambiente              = 'TesteCF';
        $this->rnc_emisor            = '';
        $this->razon_social          = '';
        $this->url_base_testecf      = 'https://ecf.dgii.gov.do/TesteCF';
        $this->url_base_ecf          = 'https://ecf.dgii.gov.do/eCF';
        $this->url_rfce_test         = 'https://ecf.dgii.gov.do/TesteCF/api/FacturasConsumidor';
        $this->url_rfce_prod         = 'https://fc.dgii.gov.do/TesteCF/api/FacturasConsumidor';
        $this->ruta_certificado_p12  = '';
        $this->password_certificado  = '';
        $this->timeout_segundos      = 30;
        $this->reintentos_maximos    = 3;
        $this->activo                = false;
        $this->cert_sujeto           = null;
        $this->cert_emisor           = null;
        $this->cert_vencimiento      = null;
    }

    public function getConfiguracion()
    {
        $lista = $this->all([], ['id' => 'ASC'], 0, 1);
        return empty($lista) ? false : $lista[0];
    }

    public function delete(): bool
    {
        // No permitimos borrar la configuración principal
        if ($this->id == 1) {
            return false;
        }
        return parent::delete();
    }

    public function save(): bool
    {
        // Ejecutamos validaciones previas
        if (false === $this->test()) {
            return false;
        }

        // Forzamos siempre el ID 1
        $this->id = 1;
        $db = $this->db ?? new \FacturaScripts\Core\Base\DataBase();

        // Limpieza de seguridad: borrar cualquier registro con ID > 1
        $db->exec("DELETE FROM " . self::tableName() . " WHERE id > 1");

        // Obtenemos los datos a guardar usando las propiedades públicas del modelo
        $data = [
            'id'                   => 1,
            'ambiente'             => $this->ambiente,
            'rnc_emisor'           => $this->rnc_emisor,
            'razon_social'         => $this->razon_social,
            'url_base_testecf'     => $this->url_base_testecf,
            'url_base_ecf'         => $this->url_base_ecf,
            'url_rfce_test'        => $this->url_rfce_test,
            'url_rfce_prod'        => $this->url_rfce_prod,
            'ruta_certificado_p12' => $this->ruta_certificado_p12,
            'password_certificado' => $this->password_certificado,
            'timeout_segundos'     => $this->timeout_segundos,
            'reintentos_maximos'   => $this->reintentos_maximos,
            'activo'               => $this->activo ? 1 : 0,
            'cert_sujeto'          => $this->cert_sujeto,
            'cert_emisor'          => $this->cert_emisor,
            'cert_vencimiento'     => $this->cert_vencimiento,
        ];

        // Comprobar si existe el ID 1
        $existe = $db->select("SELECT id FROM " . self::tableName() . " WHERE id = 1");

        if (!empty($existe)) {
            // Si existe, actualizamos (sin el campo id)
            $dataUpdate = $data;
            unset($dataUpdate['id']);
            return $db->update(self::tableName(), $dataUpdate, ['id' => 1]);
        }

        // Si no existe, insertamos manualmente para garantizar el ID 1
        $columns = [];
        $values  = [];
        foreach ($data as $key => $value) {
            $columns[] = $key;
            $values[]  = $db->escape($value);
        }

        $sql = "INSERT INTO " . self::tableName() . " (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")";
        return $db->exec($sql);
    }

    public function test(): bool
    {
        if (!in_array($this->ambiente, ['TesteCF', 'eCF'])) {
            $this->ambiente = 'TesteCF';
        }

        // Limpiar otros registros si existen (solo queremos uno)
        $db = $this->db ?? new \FacturaScripts\Core\Base\DataBase();
        $db->exec("DELETE FROM " . self::tableName() . " WHERE id > 1");

        return parent::test();
    }
}
