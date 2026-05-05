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
     * Obtiene (o refresca) el Token JWT de DGII usando el flujo de Semilla.
     * Se cachea 55 minutos.
     */
    public function obtenerToken(): string
    {
        $cacheKey = 'dgii_token_' . md5($this->config->ruta_certificado_p12 . $this->config->ambiente);

        $tokenGuardado = Cache::get($cacheKey);
        if (!empty($tokenGuardado)) {
            return $tokenGuardado;
        }

        // 1. Obtener Semilla
        $urlSemilla = str_replace('/recepcion', '/autenticacion', $this->urlBase) . '/api/Autenticacion/Semilla';
        $respSemilla = $this->httpGet($urlSemilla, []);
        if ($respSemilla['code'] !== 200) {
            throw new \RuntimeException('eCF-GMV: Error obteniendo semilla de DGII (HTTP ' . $respSemilla['code'] . ')');
        }

        // 2. Firmar Semilla
        // El XML de la semilla debe firmarse tal cual se recibe.
        $xmlSemillaFirmada = DgiiXmlFirmador::firmarECF(
            $respSemilla['body'],
            $this->config->ruta_certificado_p12,
            $this->config->password_certificado
        );

        // 3. Validar Semilla y obtener Token
        $urlValidar = str_replace('/recepcion', '/autenticacion', $this->urlBase) . '/api/Autenticacion/ValidarSemilla';
        $respToken = $this->httpPost($urlValidar, $xmlSemillaFirmada, [
            'Content-Type: application/xml'
        ]);

        $data = json_decode($respToken['body'], true);
        if (empty($data['token'])) {
            $this->registrarLog('AUTH_TOKEN_ERROR', '', $xmlSemillaFirmada, $respToken['body'], $respToken['code']);
            throw new \RuntimeException('eCF-GMV: DGII no devolvió token. Respuesta: ' . $respToken['body']);
        }

        Cache::set($cacheKey, $data['token'], 3300); // 55 min
        return $data['token'];
    }

    /**
     * Envía un e-CF firmado a DGII.
     */
    public function enviarECF(string $xmlFirmado, string $referencia, string $xmlSinFirma = ''): array
    {
        $token    = $this->obtenerToken();
        $url      = $this->urlBase . '/api/ECF';
        $response = $this->httpPost($url, $xmlFirmado, [
            'Content-Type: application/xml',
            'Authorization: Bearer ' . $token,
        ]);

        $data = json_decode($response['body'], true);
        $trackId = $data['trackId'] ?? '';

        $this->registrarLog('ENVIO_ECF', $referencia, $xmlFirmado, $response['body'], $response['code'], $xmlSinFirma, $xmlFirmado);

        return [
            'trackid' => $trackId,
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
        $url   = str_replace('/recepcion', '/consultaresultado', $this->urlBase) 
            . '/api/Consultas/Estado?rncEmisor=' . urlencode($rncEmisor) 
            . '&encf=' . urlencode($encf) 
            . '&trackId=' . urlencode($trackid);

        $response = $this->httpGet($url, [
            'Authorization: Bearer ' . $token,
        ]);

        $this->registrarLog('CONSULTA_ESTADO', $encf, '', $response['body'], $response['code']);

        $data = json_decode($response['body'], true);
        
        // La respuesta puede ser un array de objetos o un objeto único según la versión
        // Usualmente es: {"estado": 1, "mensaje": "Aceptado", ...}
        return [
            'estado'  => $data['estado']  ?? self::ESTADO_NO_ENCONTRADO,
            'mensaje' => $data['mensaje'] ?? ($data['mensajes'][0]['valor'] ?? $response['body']),
        ];
    }

    /**
     * Envía un e-CF consumidor por RFCE (e-CF tipo 32 < 250,000 DOP).
     */
    public function enviarRFCE(string $xmlFirmado, string $referencia, string $xmlSinFirma = ''): array
    {
        $token = $this->obtenerToken();
        $url   = ($this->config->ambiente === 'eCF')
            ? $this->config->url_rfce_prod
            : $this->config->url_rfce_test;

        $response = $this->httpPost($url, $xmlFirmado, [
            'Content-Type: application/xml',
            'Authorization: Bearer ' . $token,
        ]);

        $this->registrarLog('ENVIO_RFCE', $referencia, $xmlFirmado, $response['body'], $response['code'], $xmlSinFirma, $xmlFirmado);

        $data = json_decode($response['body'], true);
        return [
            'encf'    => $data['eCF']    ?? '',
            'estado'  => $data['estado'] ?? self::ESTADO_RECHAZADO,
            'mensaje' => $data['mensaje'] ?? $response['body'],
        ];
    }
    /**
     * Envía una Aprobación Comercial (ACECF) al endpoint del emisor (B2B).
     */
    public function enviarACECF(string $urlDestino, string $xmlFirmado, string $referencia, string $xmlSinFirma = ''): array
    {
        $response = $this->httpPost($urlDestino, $xmlFirmado, [
            'Content-Type: application/xml'
        ]);

        $this->registrarLog('ENVIO_ACECF', $referencia, $xmlFirmado, $response['body'], $response['code'], $xmlSinFirma, $xmlFirmado);

        return [
            'estado'  => ($response['code'] === 200 || $response['code'] === 201) ? 1 : 0,
            'mensaje' => $response['body'],
        ];
    }

    /**
     * Consulta el directorio de la DGII para obtener el endpoint de recepción de un RNC.
     */
    public function consultarDirectorio(string $rnc): ?string
    {
        try {
            $token = $this->obtenerToken();
            $url = str_replace('/recepcion', '/consultadirectorio', $this->urlBase) . '/api/Consultas/Directorio?rnc=' . urlencode($rnc);
            
            $response = $this->httpGet($url, [
                'Authorization: Bearer ' . $token,
            ]);

            if ($response['code'] !== 200) {
                return null;
            }

            $data = json_decode($response['body'], true);
            // El formato suele ser un objeto con la URL de recepción
            return $data['urlRecepcion'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
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

    private function registrarLog(string $tipo, string $referencia, string $peticion, string $respuesta, int $httpCode, string $xmlSinFirma = '', string $xmlFirmado = ''): void
    {
        $log = new ECFLog();
        $log->tipo          = $tipo;
        $log->referencia    = $referencia;
        $log->peticion      = substr($peticion, 0, 65535);
        $log->respuesta     = substr($respuesta, 0, 65535);
        $log->http_code     = $httpCode;
        $log->xml_sin_firma = $xmlSinFirma;
        $log->xml_firmado   = $xmlFirmado;
        $log->fecha         = date('Y-m-d H:i:s');
        $log->save();
    }
}
