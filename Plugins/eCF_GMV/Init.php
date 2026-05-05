<?php
/**
 * Plugin eCF-GMV: Facturación Electrónica e-CF para República Dominicana (DGII).
 *
 * Extiende fsRepublicaDominicana para añadir:
 *  - Firma digital XMLDSig (RSA-SHA256)
 *  - Comunicación con la API REST de DGII (TesteCF / eCF)
 *  - Gestión de TrackId y polling de estado mediante Cron
 *  - Soporte RFCE (e-CF consumidor < 250,000 DOP)
 */

namespace FacturaScripts\Plugins\eCF_GMV;

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFLog;

class Init extends InitClass
{
    public function init(): void
    {
        // Extender el controlador de factura de cliente
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
        
        // Extender el modelo cliente y factura
        $this->loadExtension(new Extension\Model\Cliente());
        $this->loadExtension(new Extension\Model\FacturaCliente());
    }

    public function update(): void
    {
        // Limpieza de duplicados (solo permitimos un registro con ID 1)
        $db = new \FacturaScripts\Core\Base\DataBase();
        $model = new ECFConfiguracion();
        $db->exec("DELETE FROM " . $model->tableName() . " WHERE id > 1");

        // Crear configuración por defecto si no existe
        $this->crearConfiguracionInicial();
    }



    private function crearConfiguracionInicial(): void
    {
        $modelo = new ECFConfiguracion();
        $config = $modelo->getConfiguracion();

        if (false === $config) {
            $nueva = new ECFConfiguracion();
            $nueva->ambiente            = 'TesteCF';
            $nueva->url_base_testecf    = 'https://ecf.dgii.gov.do/TesteCF';
            $nueva->url_base_ecf        = 'https://ecf.dgii.gov.do/eCF';
            $nueva->url_rfce_test       = 'https://ecf.dgii.gov.do/TesteCF/api/FacturasConsumidor';
            $nueva->url_rfce_prod       = 'https://fc.dgii.gov.do/api/FacturasConsumidor';
            $nueva->ruta_certificado_p12 = '';
            $nueva->password_certificado = '';
            $nueva->timeout_segundos    = 30;
            $nueva->reintentos_maximos  = 3;
            $nueva->activo              = false;

            if ($nueva->save()) {
                Tools::log()->info('eCF-GMV: Configuración inicial creada. Configure el certificado .p12 para activar el plugin.');
            }
        }
    }

    public function uninstall(): void
    {
        // Limpieza opcional al desinstalar
    }
}
