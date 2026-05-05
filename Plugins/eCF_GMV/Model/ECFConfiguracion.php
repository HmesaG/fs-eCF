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
        $this->url_rfce_prod         = 'https://fc.dgii.gov.do/api/FacturasConsumidor';
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
        if (false === $this->test()) {
            return false;
        }

        $this->id = 1;

        // Eliminar registros con ID > 1
        $this->db->exec("DELETE FROM " . self::tableName() . " WHERE id > 1");

        $existe = $this->db->select("SELECT id FROM " . self::tableName() . " WHERE id = 1");

        if (!empty($existe)) {
            // UPDATE usando exec() con valores escapados manualmente
            $sql = "UPDATE " . self::tableName() . " SET "
                . "ambiente = '" . addslashes($this->ambiente) . "', "
                . "rnc_emisor = '" . addslashes($this->rnc_emisor) . "', "
                . "razon_social = '" . addslashes($this->razon_social) . "', "
                . "url_base_testecf = '" . addslashes($this->url_base_testecf) . "', "
                . "url_base_ecf = '" . addslashes($this->url_base_ecf) . "', "
                . "url_rfce_test = '" . addslashes($this->url_rfce_test) . "', "
                . "url_rfce_prod = '" . addslashes($this->url_rfce_prod) . "', "
                . "ruta_certificado_p12 = '" . addslashes($this->ruta_certificado_p12) . "', "
                . "password_certificado = '" . addslashes($this->password_certificado) . "', "
                . "timeout_segundos = " . intval($this->timeout_segundos) . ", "
                . "reintentos_maximos = " . intval($this->reintentos_maximos) . ", "
                . "activo = " . ($this->activo ? 1 : 0) . ", "
                . "cert_sujeto = '" . addslashes($this->cert_sujeto) . "', "
                . "cert_emisor = '" . addslashes($this->cert_emisor) . "', "
                . "cert_vencimiento = '" . addslashes($this->cert_vencimiento) . "' "
                . "WHERE id = 1";

            return $this->db->exec($sql);
        }

        // INSERT
        $sql = "INSERT INTO " . self::tableName() . " (
            id, ambiente, rnc_emisor, razon_social, url_base_testecf, url_base_ecf,
            url_rfce_test, url_rfce_prod, ruta_certificado_p12, password_certificado,
            timeout_segundos, reintentos_maximos, activo, cert_sujeto, cert_emisor, cert_vencimiento
        ) VALUES (
            1,
            '" . addslashes($this->ambiente) . "',
            '" . addslashes($this->rnc_emisor) . "',
            '" . addslashes($this->razon_social) . "',
            '" . addslashes($this->url_base_testecf) . "',
            '" . addslashes($this->url_base_ecf) . "',
            '" . addslashes($this->url_rfce_test) . "',
            '" . addslashes($this->url_rfce_prod) . "',
            '" . addslashes($this->ruta_certificado_p12) . "',
            '" . addslashes($this->password_certificado) . "',
            " . intval($this->timeout_segundos) . ",
            " . intval($this->reintentos_maximos) . ",
            " . ($this->activo ? 1 : 0) . ",
            '" . addslashes($this->cert_sujeto) . "',
            '" . addslashes($this->cert_emisor) . "',
            '" . addslashes($this->cert_vencimiento) . "'
        )";

        return $this->db->exec($sql);
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

    public function testData(): bool
    {
        return $this->test();
    }

}
