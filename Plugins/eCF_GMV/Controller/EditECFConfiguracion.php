<?php

namespace FacturaScripts\Plugins\eCF_GMV\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;

/**
 * Controlador de edición de la configuración e-CF.
 * Permite además subir el certificado .p12 directamente desde la interfaz.
 */
class EditECFConfiguracion extends EditController
{
    /** Carpeta donde se guardan los certificados (dentro del plugin) */
    const CERT_DIR = 'Plugins/eCF_GMV/Certificados/';

    /** @var string Último error de OpenSSL detectado */
    protected $lastOpenSSLError = '';

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'Configuración e-CF';
        $pageData['menu'] = 'ecf_gmv';
        $pageData['icon'] = 'fas fa-cog';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        // FORZAR QUE EXISTA EL REGISTRO ID 1 ANTES DE CUALQUIER COSA
        $model = new \FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion();
        $existe = $model->get(1);

        if (false === $existe) {
            // Crear registro por defecto
            $model->id = 1;
            $model->ambiente = 'TesteCF';
            $model->activo = false;
            $model->rnc_emisor = '';
            $model->razon_social = '';
            $model->save();
        }

        // Forzar code=1 en la URL
        $currentCode = $this->request->query->get('code');
        if ($currentCode != 1) {
            $this->redirect('EditECFConfiguracion?code=1');
            return;
        }

        parent::privateCore($response, $user, $permissions);
    }

    public function getModelClassName(): string
    {
        return 'ECFConfiguracion';
    }

    protected function loadModelData(): void
    {
        // Forzar que siempre cargue el registro con ID 1
        $code = $this->request->query->get('code', '1');

        if (empty($code) || $code === 'null') {
            $code = '1';
            $this->request->query->set('code', '1');
        }

        parent::loadModelData();

        $model = $this->getModel();
        if (null === $model || false === $model->exists()) {
            $newModel = new \FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion();
            $newModel->id = 1;
            $newModel->ambiente = 'TesteCF';
            $newModel->activo = false;
            $newModel->save();

            parent::loadModelData();
        }
    }

    protected function createViews()
    {
        $this->addEditView('EditECFConfiguracion', 'ECFConfiguracion', 'Configuración e-CF', 'fas fa-cog');

        // FORZAR QUE LA VISTA SE CREE CON EL ID 1
        $this->setSettings('EditECFConfiguracion', 'code', '1');

        // Desactivar botones de Nuevo y Eliminar para forzar configuración única
        $this->setSettings('EditECFConfiguracion', 'btnNew', false);
        $this->setSettings('EditECFConfiguracion', 'btnDelete', false);
        $this->setSettings('EditECFConfiguracion', 'allowNew', false);
        $this->setSettings('EditECFConfiguracion', 'allowDelete', false);

        // Mostrar advertencias de estado del certificado
        $model = $this->getModel();
        $fullPath = $this->getCertFullPath((string)$model->ruta_certificado_p12);

        if ($model->cert_vencimiento) {
            $vencimiento = strtotime($model->cert_vencimiento);
            $hoy         = time();
            if ($vencimiento < $hoy) {
                Tools::log()->error('El certificado digital ha EXPIRADO el ' . $model->cert_vencimiento . '. Por favor, suba uno nuevo.');
            } elseif ($vencimiento < ($hoy + (30 * 24 * 60 * 60))) {
                Tools::log()->warning('El certificado digital vencerá pronto (' . $model->cert_vencimiento . ').');
            }
        }

        // Botón para verificar contraseña manualmente
        $this->addButton('EditECFConfiguracion', [
            'row'    => 'footer-actions',
            'action' => 'verify-cert',
            'color'  => 'warning',
            'icon'   => 'fas fa-key',
            'label'  => 'Verificar Contraseña',
            'type'   => 'modal',
        ]);

        // Botón para abrir el modal de subida del certificado
        $this->addButton('EditECFConfiguracion', [
            'row'    => 'footer-actions',
            'action' => 'upload-cert',
            'color'  => 'info',
            'icon'   => 'fas fa-upload',
            'label'  => 'Subir Certificado Digital .p12',
            'type'   => 'modal',
        ]);
    }

    /**
     * Intercepta acciones personalizadas antes de cargar los datos.
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'upload-cert':
                return $this->uploadCertificateAction();
            case 'test-cert':
                return $this->testCertificateAction();
            case 'test-conexion':
                return $this->testConexionAction();
            case 'verify-cert':
                return $this->verifyCertificateAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Procesa la subida del archivo .p12 y actualiza la ruta en la configuración.
     */
    protected function uploadCertificateAction(): bool
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return true;
        }

        $uploadFile = $this->request->files->get('cert_file');
        if (empty($uploadFile)) {
            Tools::log()->warning('No se seleccionó ningún archivo.');
            return true;
        }

        $extension = strtolower($uploadFile->getClientOriginalExtension());
        if ($extension !== 'p12') {
            Tools::log()->error('Solo se permiten archivos .p12');
            return true;
        }

        // Obtener contraseña de la configuración actual
        $model = $this->getModel();
        $password = (string)$model->password_certificado;

        if (empty($password)) {
            Tools::log()->error('Debe configurar la contraseña del certificado antes de subirlo.');
            return true;
        }

        // Validar el certificado con la contraseña guardada
        $certInfo = $this->readCertificateInfo($uploadFile->getPathname(), $password);
        if (false === $certInfo) {
            Tools::log()->error('Error al validar certificado: ' . $this->lastOpenSSLError);
            return true;
        }

        // Crear carpeta Private/Certificados si no existe
        $certDir = FS_FOLDER . DIRECTORY_SEPARATOR . 'Private' . DIRECTORY_SEPARATOR . 'Certificados' . DIRECTORY_SEPARATOR;
        if (!is_dir($certDir) && !mkdir($certDir, 0755, true)) {
            Tools::log()->error('No se pudo crear la carpeta Private/Certificados');
            return true;
        }

        // Generar nombre seguro con timestamp
        $filename = 'cert_' . date('Ymd_His') . '.p12';
        $destPath = $certDir . $filename;

        if (false === move_uploaded_file($uploadFile->getPathname(), $destPath)) {
            Tools::log()->error('Error al guardar el archivo');
            return true;
        }

        // Actualizar configuración
        $model->id = 1;
        $model->ruta_certificado_p12 = 'Private/Certificados/' . $filename;
        $model->cert_sujeto = $certInfo['sujeto'];
        $model->cert_emisor = $certInfo['emisor'];
        $model->cert_vencimiento = $certInfo['vencimiento'];

        if ($model->save()) {
            Tools::log()->notice('Certificado subido y validado con éxito.');
            $this->redirect('EditECFConfiguracion?code=1');
            return false;
        }

        Tools::log()->error('No se pudo guardar la configuración.');
        return true;
    }

    /**
     * Lee la información del certificado .p12 usando OpenSSL con fallback de Shell.
     */
    protected function readCertificateInfo(string $p12Path, string $password = ''): array|false
    {
        // Si el archivo existe directamente (ej. temporal de subida), lo usamos.
        // Si no, intentamos resolver la ruta completa.
        $fullPath = file_exists($p12Path) ? $p12Path : $this->getCertFullPath($p12Path);
        
        if (empty($fullPath) || !file_exists($fullPath)) {
            $this->lastOpenSSLError = 'Archivo no encontrado: ' . $p12Path;
            return false;
        }

        // Para OpenSSL 3.x - Usar legacy provider
        $cmd = "openssl pkcs12 -in " . escapeshellarg($fullPath)
             . " -passin pass:" . escapeshellarg($password)
             . " -legacy -nodes -clcerts -nokeys 2>&1";

        $output = shell_exec($cmd);

        if ($output && strpos($output, '-----BEGIN CERTIFICATE-----') !== false) {
            if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $output, $matches)) {
                return $this->parseCertData($matches[0]);
            }
        }

        $this->lastOpenSSLError = 'Contraseña incorrecta o certificado no válido. ' . substr((string)$output, 0, 200);
        return false;
    }

    /**
     * Parsea un certificado en formato PEM para extraer info.
     */
    protected function verifyCertificateAction(): bool
    {
        $model = $this->getModel();
        $fullPath = $this->getCertFullPath((string)$model->ruta_certificado_p12);

        if (empty($model->ruta_certificado_p12)) {
            Tools::log()->warning('No hay ningún certificado configurado.');
            return true;
        }

        $password = $this->request->request->get('verify_password');

        if (empty($password)) {
            Tools::log()->warning('Debe ingresar una contraseña para verificar.');
            return true;
        }

        // Probar la contraseña
        $cmd = "openssl pkcs12 -in " . escapeshellarg($fullPath)
             . " -passin pass:" . escapeshellarg($password)
             . " -legacy -noout -info 2>&1";

        $output = shell_exec($cmd);

        if (strpos($output, 'MAC verified OK') !== false || strpos($output, 'PKCS7') !== false) {
            Tools::log()->notice('✓ CONTRASEÑA CORRECTA. El certificado es válido.');
        } elseif (strpos($output, 'mac verify failure') !== false) {
            Tools::log()->error('✗ CONTRASEÑA INCORRECTA. Verifique la contraseña del certificado.');
        } elseif (strpos($output, 'unsupported') !== false) {
            Tools::log()->error('✗ Error de OpenSSL: El certificado usa algoritmos no soportados.');
        } else {
            Tools::log()->error('✗ Error desconocido: ' . substr((string)$output, 0, 200));
        }

        return true;
    }

    /**
     * Parsea un certificado en formato PEM para extraer info.
     */
    protected function parseCertData(string $pemData): array|false
    {
        $certInfo = openssl_x509_parse($pemData);
        if (!$certInfo) {
            return false;
        }

        return [
            'sujeto'      => $this->parseDN($certInfo['subject'] ?? []),
            'emisor'      => $this->parseDN($certInfo['issuer'] ?? []),
            'vencimiento' => isset($certInfo['validTo_time_t']) ? date('Y-m-d', $certInfo['validTo_time_t']) : null,
        ];
    }

    /**
     * Convierte un array de DN a string legible.
     */
    protected function parseDN(array $dn): string
    {
        if (!empty($dn['CN'])) {
            $result = $dn['CN'];
            if (!empty($dn['O'])) { $result .= ' (' . $dn['O'] . ')'; }
            return $result;
        }
        $parts = [];
        foreach ($dn as $key => $value) {
            $parts[] = $key . '=' . (is_array($value) ? implode(',', $value) : $value);
        }
        return implode(', ', $parts);
    }

    /**
     * Prueba si el certificado actual se puede abrir con la contraseña configurada.
     */
    protected function testCertificateAction(): bool
    {
        $model = $this->getModel();
        $configList = $model->all([], ['id' => 'ASC'], 0, 1);
        if (!empty($configList)) {
            $model = $configList[0];
        }

        $fullPath = $this->getCertFullPath((string)$model->ruta_certificado_p12);

        if (empty($model->ruta_certificado_p12)) {
            Tools::log()->warning('No hay ningún certificado configurado.');
            return true;
        }

        $password = $this->request->request->get('password_certificado') ?: (string)$model->password_certificado;
        $certInfo = $this->readCertificateInfo($fullPath, $password);
        if ($certInfo) {
            Tools::log()->notice('¡Certificado verificado con éxito!');
            $model->cert_sujeto      = $certInfo['sujeto'];
            $model->cert_emisor      = $certInfo['emisor'];
            $model->cert_vencimiento = $certInfo['vencimiento'];
            $model->save();
            $this->redirect('EditECFConfiguracion?code=' . $model->id);
            return false;
        }

        Tools::log()->error('Error: ' . $this->lastOpenSSLError);
        return true;
    }

    /**
     * Prueba la comunicación con DGII solicitando un token.
     */
    protected function testConexionAction(): bool
    {
        try {
            $api = new \FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiApiService();
            $token = $api->obtenerToken();
            if ($token) {
                Tools::log()->notice('¡Conexión con DGII exitosa! Token obtenido correctamente.');
            }
        } catch (\Exception $e) {
            Tools::log()->error('Error de conexión con DGII: ' . $e->getMessage());
        }
        return true;
    }

    /**
     * Retorna la ruta absoluta del certificado.
     */
    protected function getCertFullPath(string $relativeOrAbsolute): string
    {
        if (empty($relativeOrAbsolute)) {
            return '';
        }

        // Si es ruta absoluta y existe, usarla
        if (file_exists($relativeOrAbsolute)) {
            return $relativeOrAbsolute;
        }

        // Buscar en Private/Certificados/ (Nueva ubicación segura)
        $privatePath = FS_FOLDER . DIRECTORY_SEPARATOR . 'Private' . DIRECTORY_SEPARATOR . 'Certificados' . DIRECTORY_SEPARATOR . basename($relativeOrAbsolute);
        if (file_exists($privatePath)) {
            return $privatePath;
        }

        // Buscar en la ruta relativa original (Fallback)
        $fullPath = FS_FOLDER . DIRECTORY_SEPARATOR . $relativeOrAbsolute;
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // Buscar en la carpeta del plugin (Legacy)
        $legacyPath = FS_FOLDER . DIRECTORY_SEPARATOR . 'Plugins' . DIRECTORY_SEPARATOR . 'eCF_GMV' . DIRECTORY_SEPARATOR . 'Certificados' . DIRECTORY_SEPARATOR . basename($relativeOrAbsolute);
        if (file_exists($legacyPath)) {
            return $legacyPath;
        }

        return '';
    }
}
