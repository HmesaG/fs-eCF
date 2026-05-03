<?php
/**
 * Servicio centralizado de comunicación con la API REST de DGII.
 * Gestiona: autenticación JWT, envío de e-CF, consulta de estado, RFCE.
 */

namespace FacturaScripts\Plugins\eCF_GMV\Lib\DGII;

use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFLog;

class DgiiApiService
{
    // Constantes de estado DGII
    const ESTADO_NO_ENCONTRADO  = 0;
    const ESTADO_ACEPTADO       = 1;
    const ESTADO_RECHAZADO      = 2;
    const ESTADO_EN_PROCESO     = 3;
    const ESTADO_ACEPTADO_COND  = 4;
    const ESTADO_RECHAZADO_XML  = 5;

    public static function getStatusText(int $status): string
    {
        $map = [
            self::ESTADO_ACEPTADO       => 'Aceptada',
            self::ESTADO_RECHAZADO      => 'Rechazada',
            self::ESTADO_EN_PROCESO     => 'En Proceso (pendiente confirmación)',
            self::ESTADO_ACEPTADO_COND  => 'Aceptada Condicionalmente',
            self::ESTADO_NO_ENCONTRADO  => 'No Encontrada en DGII',
            self::ESTADO_RECHAZADO_XML  => 'Rechazada por error en XML',
        ];
        return $map[$status] ?? 'Desconocido';
    }

    /** @var ECFConfiguracion */
    private ECFConfiguracion $config;

    /** @var string URL base según el ambiente */
    private string $urlBase;

    public function __construct()
    {
        $config = (new ECFConfiguracion())->getConfiguracion();
        if (!$config) {
            throw new \RuntimeException('eCF-GMV: No hay configuración DGII guardada.');
        }
        $this->config  = $config;
        $this->urlBase = ($config->ambiente === 'eCF') ? $config->url_base_ecf : $config->url_base_testecf;
    }

    /**
     * Obtiene (o refresca) el Token JWT de DGII. Se cachea 55 minutos.
     */
    public function obtenerToken(): string
    {
        $cacheKey = 'dgii_token_' . md5($this->config->ruta_certificado_p12);

        $tokenGuardado = Cache::get($cacheKey);
        if (!empty($tokenGuardado)) {
            return $tokenGuardado;
        }

        // Extraer certificado del .p12
        $p12data = file_get_contents($this->config->ruta_certificado_p12);
        $certs   = [];
        if (!openssl_pkcs12_read($p12data, $certs, $this->config->password_certificado)) {
            throw new \RuntimeException('eCF-GMV: No se pudo leer el certificado .p12. Verifique la contraseña.');
        }

        $url      = $this->urlBase . '/api/Autenticacion/tokens';
        $response = $this->httpPost($url, '', [
            'Content-Type: application/json',
            'Authorization: ' . $this->buildCertHeader($certs),
        ]);

        $data = json_decode($response['body'], true);
        if (empty($data['access_token'])) {
            $this->registrarLog('AUTH_TOKEN', '', '', $response['body'], $response['code']);
            throw new \RuntimeException('eCF-GMV: DGII no devolvió token. Respuesta: ' . $response['body']);
        }

        Cache::set($cacheKey, $data['access_token'], 3300); // 55 min
        return $data['access_token'];
    }

    /**
     * Envía un e-CF firmado a DGII.
     */
    public function enviarECF(string $xmlFirmado, string $referencia): array
    {
        $token    = $this->obtenerToken();
        $url      = $this->urlBase . '/api/ECF';
        $response = $this->httpPost($url, $xmlFirmado, [
            'Content-Type: application/xml',
            'Authorization: Bearer ' . $token,
        ]);

        $this->registrarLog('ENVIO_ECF', $referencia, $xmlFirmado, $response['body'], $response['code']);

        $data = json_decode($response['body'], true);
        return [
            'trackid' => $data['trackId'] ?? '',
            'estado'  => $data['estado']  ?? self::ESTADO_EN_PROCESO,
            'mensaje' => $data['mensaje'] ?? $response['body'],
        ];
    }

    /**
     * Consulta el estado de un e-CF por TrackId.
     */
    public function consultarEstado(string $rncEmisor, string $encf, string $trackid): array
    {
        $token = $this->obtenerToken();
        $url   = $this->urlBase . '/api/ECF/' . urlencode($rncEmisor) . '/' . urlencode($encf) . '/' . urlencode($trackid);

        $response = $this->httpGet($url, [
            'Authorization: Bearer ' . $token,
        ]);

        $this->registrarLog('CONSULTA_ESTADO', $encf, '', $response['body'], $response['code']);

        $data = json_decode($response['body'], true);
        return [
            'estado'  => $data['estado']  ?? self::ESTADO_NO_ENCONTRADO,
            'mensaje' => $data['mensaje'] ?? $response['body'],
        ];
    }

    /**
     * Envía un e-CF consumidor por RFCE (e-CF tipo 32 < 250,000 DOP).
     */
    public function enviarRFCE(string $xmlFirmado, string $referencia): array
    {
        $token = $this->obtenerToken();
        $url   = ($this->config->ambiente === 'eCF')
            ? $this->config->url_rfce_prod
            : $this->config->url_rfce_test;

        $response = $this->httpPost($url, $xmlFirmado, [
            'Content-Type: application/xml',
            'Authorization: Bearer ' . $token,
        ]);

        $this->registrarLog('ENVIO_RFCE', $referencia, $xmlFirmado, $response['body'], $response['code']);

        $data = json_decode($response['body'], true);
        return [
            'encf'    => $data['eCF']    ?? '',
            'estado'  => $data['estado'] ?? self::ESTADO_RECHAZADO,
            'mensaje' => $data['mensaje'] ?? $response['body'],
        ];
    }

    // -----------------------------------------------------------------------
    // HTTP helpers
    // -----------------------------------------------------------------------

    private function httpPost(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->config->timeout_segundos,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $respBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($respBody === false) {
            throw new \RuntimeException('eCF-GMV HTTP error: ' . $error);
        }

        return ['body' => $respBody, 'code' => $httpCode];
    }

    private function httpGet(string $url, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->config->timeout_segundos,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $respBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($respBody === false) {
            throw new \RuntimeException('eCF-GMV HTTP error: ' . $error);
        }

        return ['body' => $respBody, 'code' => $httpCode];
    }

    private function buildCertHeader(array $certs): string
    {
        // Extraer el certificado público en base64 para cabecera de autenticación
        $certClean = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $certs['cert']);
        return 'cert ' . trim($certClean);
    }

    private function registrarLog(string $tipo, string $referencia, string $peticion, string $respuesta, int $httpCode): void
    {
        $log = new ECFLog();
        $log->tipo       = $tipo;
        $log->referencia = $referencia;
        $log->peticion   = substr($peticion, 0, 65535);
        $log->respuesta  = substr($respuesta, 0, 65535);
        $log->http_code  = $httpCode;
        $log->fecha      = date('Y-m-d H:i:s');
        $log->save();
    }
}
