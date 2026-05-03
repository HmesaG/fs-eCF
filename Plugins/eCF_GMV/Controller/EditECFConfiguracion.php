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

    public function privateCore(&$response, $user, $permissions): void
    {
        $currentCode = $this->request->query->get('code') ?? $this->request->request->get('code');
        
        // Si no hay código o no es 1, intentamos cargar el registro 1
        if ($currentCode != 1) {
            $model = new \FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion();
            $config = $model->getConfiguracion();
            
            if (false === $config) {
                // Si no existe ni el 1, lo creamos
                $model->id = 1;
                $model->ambiente = 'TesteCF';
                $model->save();
            }
            
            // Redirigir siempre a code=1 usando Tools::redirect para mayor fiabilidad
            Tools::redirect(Tools::url('EditECFConfiguracion', ['code' => 1]));
            return;
        }

        parent::privateCore($response, $user, $permissions);
    }

    public function getModelClassName(): string
    {
        return 'ECFConfiguracion';
    }

    protected function createViews()
    {
        $this->addEditView('EditECFConfiguracion', 'ECFConfiguracion', 'Configuración e-CF', 'fas fa-cog');
        
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

        // Botón para verificar el certificado con la contraseña actual
        $this->addButton('EditECFConfiguracion', [
            'row'    => 'footer-actions',
            'action' => 'test-cert',
            'color'  => 'secondary',
            'icon'   => 'fas fa-check-circle',
            'label'  => 'Verificar Contraseña',
        ]);

        // Botón para abrir el modal de subida del certificado
        $this->addButton('EditECFConfiguracion', [
            'row'    => 'footer-actions',
            'action' => 'upload-cert',
            'color'  => 'info',
            'icon'   => 'fas fa-upload',
            'label'  => 'Subir Certificado .p12',
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

        // Validar extensión
        $extension = strtolower($uploadFile->getClientOriginalExtension());
        if ($extension !== 'p12') {
            Tools::log()->error('Solo se permiten archivos con extensión .p12');
            return true;
        }

        // Leer la contraseña (del modal o de la BD si ya existe)
        $password = (string)$this->request->request->get('password_certificado_modal');
        if ($password === '') {
            $model = $this->getModel();
            $password = (string)$model->password_certificado;
        }

        // Carpeta destino
        $certDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, self::CERT_DIR);
        $destDir = FS_FOLDER . DIRECTORY_SEPARATOR . $certDir;

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            Tools::log()->error('Error: No se pudo crear la carpeta ' . $destDir);
            return true;
        }

        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $uploadFile->getClientOriginalName());
        $destPath = $destDir . $filename;

        // Validar el certificado antes de moverlo definitivamente
        $certInfo = $this->readCertificateInfo($uploadFile->getPathname(), $password);
        if (false === $certInfo) {
            Tools::log()->error('Error al validar certificado: ' . $this->lastOpenSSLError);
            return true;
        }

        if (false === move_uploaded_file($uploadFile->getPathname(), $destPath)) {
            Tools::log()->error('Error: Falló move_uploaded_file. Revise permisos.');
            return true;
        }

        // Aseguramos que estamos sobre el ID 1
        $model = $this->getModel();
        $model->id = 1;
        $model->ruta_certificado_p12 = self::CERT_DIR . $filename;
        
        // Solo actualizamos el password si se proporcionó uno nuevo en el modal
        if ($this->request->request->get('password_certificado_modal') !== '') {
            $model->password_certificado = $password;
        }
        
        $model->cert_sujeto      = $certInfo['sujeto'];
        $model->cert_emisor      = $certInfo['emisor'];
        $model->cert_vencimiento = $certInfo['vencimiento'];

        if ($model->save()) {
            Tools::log()->notice('Certificado subido y validado con éxito.');
            $this->redirect($this->url() . '?code=1');
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
        if (!function_exists('openssl_pkcs12_read')) {
            $this->lastOpenSSLError = 'La extensión openssl no está disponible en PHP.';
            return false;
        }

        $fullPath = $this->getCertFullPath($p12Path);
        if (empty($fullPath) || !file_exists($fullPath)) {
            $this->lastOpenSSLError = 'Archivo no encontrado: ' . $fullPath;
            return false;
        }

        $p12Data = file_get_contents($fullPath);
        $this->lastOpenSSLError = '';

        // INTENTO 1: PHP Extension con OPENSSL_CONF (Legacy Provider)
        $cnfPath = FS_FOLDER . DIRECTORY_SEPARATOR . 'Plugins' . DIRECTORY_SEPARATOR . 'eCF_GMV' . DIRECTORY_SEPARATOR . 'openssl.cnf';
        if (file_exists($cnfPath)) {
            putenv("OPENSSL_CONF=" . $cnfPath);
        }

        $certs = [];
        if (@openssl_pkcs12_read($p12Data, $certs, $password) && !empty($certs['cert'])) {
            return $this->parseCertData($certs['cert']);
        }

        // INTENTO 2: Fallback vía Comando OpenSSL (Más robusto en Docker/OpenSSL 3.0)
        $escapedPath = escapeshellarg($fullPath);
        $escapedPass = escapeshellarg($password);
        
        // Comando para extraer el certificado en formato PEM usando legacy provider
        $cmd = "openssl pkcs12 -in $escapedPath -passin pass:$escapedPass -nodes -legacy -clcerts -nokeys 2>&1";
        $output = shell_exec($cmd);
        
        if ($output && strpos($output, '-----BEGIN CERTIFICATE-----') !== false) {
            if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $output, $matches)) {
                return $this->parseCertData($matches[0]);
            }
        }

        // Si falló todo, capturamos el error detallado
        $sslError = '';
        while ($msg = openssl_error_string()) { $sslError .= $msg . '; '; }
        
        if ($output) {
            $cleanOutput = str_replace($password, '********', $output);
            $sslError .= " [Shell Output]: " . trim($cleanOutput);
        }
        
        $this->lastOpenSSLError = $sslError ?: 'Contraseña incorrecta o formato incompatible (DGII Legacy)';
        return false;
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
            $this->redirect($this->url() . '?code=' . $model->id);
            return false;
        }

        Tools::log()->error('Error: ' . $this->lastOpenSSLError);
        return true;
    }

    /**
     * Retorna la ruta absoluta del certificado.
     */
    protected function getCertFullPath(string $relativeOrAbsolute): string
    {
        if (empty($relativeOrAbsolute)) { return ''; }

        if (strpos($relativeOrAbsolute, ':') !== false || strpos($relativeOrAbsolute, '/') === 0) {
            if (file_exists($relativeOrAbsolute)) { return $relativeOrAbsolute; }
            $relativeOrAbsolute = self::CERT_DIR . basename($relativeOrAbsolute);
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeOrAbsolute);
        $fullPath = FS_FOLDER . DIRECTORY_SEPARATOR . $path;
        
        if (!file_exists($fullPath)) {
            $fallback = FS_FOLDER . DIRECTORY_SEPARATOR . 'Plugins' . DIRECTORY_SEPARATOR . 'eCF_GMV' . DIRECTORY_SEPARATOR . 'Certificados' . DIRECTORY_SEPARATOR . basename($relativeOrAbsolute);
            if (file_exists($fallback)) { return $fallback; }
        }
        
        return $fullPath;
    }
}
