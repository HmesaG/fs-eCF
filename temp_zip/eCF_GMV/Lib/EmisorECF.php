<?php
/**
 * Servicio de emisión de e-CF: orquesta todo el flujo de envío de una factura.
 */

namespace FacturaScripts\Plugins\eCF_GMV\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiApiService;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiXmlFirmador;
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiXmlGenerador;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFTrackingPendiente;

class EmisorECF
{
    /** RD$ mínimo para envío normal (>= umbral) vs RFCE (< umbral) */
    const UMBRAL_RFCE = 250000.00;

    /** @var ECFConfiguracion */
    private ECFConfiguracion $config;

    /** @var DgiiApiService */
    private DgiiApiService $api;

    public function __construct()
    {
        $config = (new ECFConfiguracion())->getConfiguracion();
        if (!$config) {
            throw new \RuntimeException('Plugin eCF-GMV no configurado. Configure el certificado y active el plugin.');
        }
        if (!$config->activo) {
            throw new \RuntimeException('Plugin eCF-GMV está desactivado. Actívelo en la configuración.');
        }

        $this->config = $config;
        $this->api    = new DgiiApiService();
    }

    /**
     * Emite un e-CF para una FacturaCliente.
     *
     * @return array ['ok', 'trackid', 'estado', 'mensaje', 'xml']
     */
    public function emitir(FacturaCliente $factura): array
    {
        if (empty($factura->numeroncf)) {
            return $this->error('La factura no tiene e-NCF asignado. Verifique la configuración de rangos NCF.');
        }

        if (!$this->esElectronico($factura->tipocomprobante)) {
            return $this->error('El tipo de comprobante ' . $factura->tipocomprobante . ' no es electrónico (e-CF).');
        }

        // Enrutar según tipo
        $esRFCE = ($factura->tipocomprobante === '32' && (float)$factura->total < self::UMBRAL_RFCE);

        // Generar XML
        $datos        = $this->construirDatosXml($factura);
        $xmlSinFirmar = $esRFCE
            ? DgiiXmlGenerador::generarRFCE($datos)
            : DgiiXmlGenerador::generarECF($datos);

        // Firmar XML
        $xmlFirmado = DgiiXmlFirmador::firmarECF(
            $xmlSinFirmar,
            $this->config->ruta_certificado_p12,
            $this->config->password_certificado
        );

        $factura->ecf_xml_firmado = $xmlFirmado;
        $factura->ecf_fecha_firma = date('Y-m-d H:i:s');

        // Enrutar según tipo
        $esRFCE = ($factura->tipocomprobante === '32' && $factura->total < self::UMBRAL_RFCE);
        return $esRFCE
            ? $this->enviarRFCE($factura, $xmlFirmado, $xmlSinFirmar)
            : $this->enviarNormal($factura, $xmlFirmado, $xmlSinFirmar);
    }

    private function enviarNormal(FacturaCliente $factura, string $xmlFirmado, string $xmlSinFirma = ''): array
    {
        $resultado = $this->api->enviarECF($xmlFirmado, $factura->numero2 ?? $factura->codigo, $xmlSinFirma);

        $factura->ecf_trackid     = $resultado['trackid'];
        $factura->ecf_estado_dgii = $resultado['estado'];
        $factura->save();

        if ((int)$resultado['estado'] === DgiiApiService::ESTADO_EN_PROCESO && !empty($resultado['trackid'])) {
            $this->encolarPendiente($factura, $resultado['trackid']);
        }

        return [
            'ok'      => in_array((int)$resultado['estado'], [DgiiApiService::ESTADO_ACEPTADO, DgiiApiService::ESTADO_ACEPTADO_COND, DgiiApiService::ESTADO_EN_PROCESO], true),
            'trackid' => $resultado['trackid'],
            'estado'  => $resultado['estado'],
            'mensaje' => $resultado['mensaje'],
            'xml'     => $xmlFirmado,
        ];
    }

    private function enviarRFCE(FacturaCliente $factura, string $xmlFirmado, string $xmlSinFirma = ''): array
    {
        $resultado = $this->api->enviarRFCE($xmlFirmado, $factura->numero2 ?? $factura->codigo, $xmlSinFirma);

        if (!empty($resultado['encf'])) {
            $factura->numeroncf = $resultado['encf'];
        }
        $factura->ecf_estado_dgii = (int)$resultado['estado'];
        $factura->save();

        return [
            'ok'      => ((int)$resultado['estado'] === DgiiApiService::ESTADO_ACEPTADO),
            'trackid' => null,
            'estado'  => $resultado['estado'],
            'mensaje' => $resultado['mensaje'],
            'xml'     => $xmlFirmado,
        ];
    }

    private function esElectronico(string $tipo): bool
    {
        return in_array($tipo, ['31', '32', '33', '34', '41', '43', '44', '45', '46', '47'], true);
    }

    private function construirDatosXml(FacturaCliente $factura): array
    {
        $empresa = Tools::settings('default');
        $lineas  = $factura->getLines();
        $items   = [];

        foreach ($lineas as $linea) {
            $items[] = [
                'descripcion' => $linea->descripcion,
                'cantidad'    => $linea->cantidad,
                'precio'      => $linea->pvpunitario,
                'descuento'   => ($linea->pvpunitario * $linea->dtopor / 100),
                'itbis_tasa'  => ($linea->iva > 0 ? (int)$linea->iva : 18),
                'itbis_monto' => $linea->pvpsindto * ($linea->iva / 100) * $linea->cantidad,
                'exento'      => ($linea->iva == 0),
            ];
        }

        return [
            'encf'             => $factura->numeroncf,
            'tipoecf'          => $factura->tipocomprobante,
            'fecha_emision'    => $factura->fecha,
            'fecha_venc_seq'   => $factura->ncffechavencimiento ?? date('Y-12-31', strtotime('+1 year')),
            'total_neto'       => $factura->total,
            'total_itbis'      => $factura->totaliva,
            'moneda'           => $factura->coddivisa ?? 'DOP',
            'forma_pago'       => $this->mapearFormaPago($factura->ncftipopago ?? '01'),
            'emisor_rnc'       => $empresa['rnnempresa'] ?? '',
            'emisor_nombre'    => $empresa['nombrecorto'] ?? $empresa['nombre'] ?? '',
            'emisor_direccion' => $empresa['dirección'] ?? '',
            'emisor_email'     => $empresa['email'] ?? '',
            'comprador_rnc'    => $factura->cifnif ?? '',
            'comprador_nombre' => $factura->nombrecliente ?? '',
            'ncf_modificado'   => $factura->codigorect ?? null,
            'items'            => $items,
        ];
    }

    private function mapearFormaPago(string $codigo): string
    {
        return [
            '01' => 'Efectivo', '02' => 'Cheque', '03' => 'TransferenciaBancaria',
            '04' => 'TarjetaCreditoDebito', '05' => 'Bonos', '06' => 'Permuta',
            '07' => 'Credito', '08' => 'Otro',
        ][$codigo] ?? 'Efectivo';
    }

    private function encolarPendiente(FacturaCliente $factura, string $trackid): void
    {
        $p = new ECFTrackingPendiente();
        $p->encf             = $factura->numeroncf;
        $p->rnc_emisor       = Tools::settings('default', 'rnnempresa');
        $p->trackid          = $trackid;
        $p->referencia_local = $factura->idfactura;
        $p->tipo_documento   = 'FacturaCliente';
        $p->estado           = '3';
        $p->intentos         = 0;
        $p->fecha_envio      = date('Y-m-d H:i:s');
        $p->resuelto         = false;
        $p->save();
    }

    private function error(string $mensaje): array
    {
        return ['ok' => false, 'trackid' => null, 'estado' => DgiiApiService::ESTADO_RECHAZADO, 'mensaje' => $mensaje, 'xml' => ''];
    }
}
