<?php
/**
 * Generador de XML e-CF para DGII.
 * Soporta tipos: 31 (Factura de Crédito Fiscal), 32 (Factura Consumidor Final),
 *                33 (Nota de Débito), 34 (Nota de Crédito).
 */

namespace FacturaScripts\Plugins\eCF_GMV\Lib\DGII;

class DgiiXmlGenerador
{
    /**
     * Genera el XML de e-CF según los datos de la factura.
     *
     * @param array $datos Datos extraídos de la FacturaCliente (ver EmisorECF::construirDatosXml)
     * @return string XML UTF-8 sin firma
     */
    public static function generarECF(array $datos): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('ECF');
        $dom->appendChild($root);

        // Encabezado
        self::agregarEncabezado($dom, $root, $datos);

        // Ítems
        self::agregarItems($dom, $root, $datos['items']);

        // Totales
        self::agregarTotales($dom, $root, $datos);

        return $dom->saveXML();
    }

    // -----------------------------------------------------------------------

    private static function agregarEncabezado(\DOMDocument $dom, \DOMElement $root, array $d): void
    {
        $enc = $dom->createElement('Encabezado');
        $root->appendChild($enc);

        self::addNode($dom, $enc, 'Version', '1.0');
        self::addNode($dom, $enc, 'IdDoc', $d['encf']);
        self::addNode($dom, $enc, 'TipoeCF', $d['tipoecf']);
        self::addNode($dom, $enc, 'eNCF', $d['encf']);
        self::addNode($dom, $enc, 'FechaVencimientoSecuencia', $d['fecha_venc_seq']);
        self::addNode($dom, $enc, 'IndicadorNotaCredito', '0');
        self::addNode($dom, $enc, 'IndicadorNotaDebito', '0');

        // Emisor
        $emisor = $dom->createElement('Emisor');
        $enc->appendChild($emisor);
        self::addNode($dom, $emisor, 'RNCEmisor', $d['emisor_rnc']);
        self::addNode($dom, $emisor, 'RazonSocialEmisor', $d['emisor_nombre']);
        if (!empty($d['emisor_direccion'])) {
            self::addNode($dom, $emisor, 'DireccionEmisor', $d['emisor_direccion']);
        }
        if (!empty($d['emisor_email'])) {
            self::addNode($dom, $emisor, 'CorreoEmisor', $d['emisor_email']);
        }

        // Comprador (solo tipos 31, 33, 34)
        if (in_array($d['tipoecf'], ['31', '33', '34'], true) && !empty($d['comprador_rnc'])) {
            $comp = $dom->createElement('Comprador');
            $enc->appendChild($comp);
            self::addNode($dom, $comp, 'RNCComprador', $d['comprador_rnc']);
            self::addNode($dom, $comp, 'RazonSocialComprador', $d['comprador_nombre']);
        }

        // Fecha
        self::addNode($dom, $enc, 'FechaEmision', date('Y-m-d', strtotime($d['fecha_emision'])));
        self::addNode($dom, $enc, 'HoraEmision', date('H:i:s', strtotime($d['fecha_emision'])));

        // Referencia NCF original (NC/ND)
        if (!empty($d['ncf_modificado'])) {
            $ref = $dom->createElement('InformacionReferencia');
            $enc->appendChild($ref);
            self::addNode($dom, $ref, 'NCFModificado', $d['ncf_modificado']);
        }

        // Forma de pago
        $infoPago = $dom->createElement('InformacionPago');
        $enc->appendChild($infoPago);
        self::addNode($dom, $infoPago, 'FormaDePago', $d['forma_pago']);
        self::addNode($dom, $infoPago, 'Moneda', $d['moneda'] ?? 'DOP');
    }

    private static function agregarItems(\DOMDocument $dom, \DOMElement $root, array $items): void
    {
        $detalles = $dom->createElement('DetallesItems');
        $root->appendChild($detalles);

        $num = 1;
        foreach ($items as $item) {
            $linea = $dom->createElement('Item');
            $detalles->appendChild($linea);

            self::addNode($dom, $linea, 'NumeroLinea', (string)$num++);
            self::addNode($dom, $linea, 'IndicadorFacturacion', $item['exento'] ? '3' : '1');
            self::addNode($dom, $linea, 'NombreItem', $item['descripcion']);
            self::addNode($dom, $linea, 'CantidadItem', self::fmt($item['cantidad']));
            self::addNode($dom, $linea, 'PrecioUnitarioItem', self::fmt($item['precio']));
            self::addNode($dom, $linea, 'TablaSubDescuento', '');
            self::addNode($dom, $linea, 'MontoItem', self::fmt($item['precio'] * $item['cantidad']));

            if (!$item['exento']) {
                self::addNode($dom, $linea, 'TasaITBIS', (string)(int)$item['itbis_tasa']);
                self::addNode($dom, $linea, 'MontoITBIS', self::fmt($item['itbis_monto']));
            }
        }
    }

    private static function agregarTotales(\DOMDocument $dom, \DOMElement $root, array $d): void
    {
        $tot = $dom->createElement('Totales');
        $root->appendChild($tot);

        $neto    = $d['total_neto'] - $d['total_itbis'];
        $totalI  = $d['total_itbis'];

        self::addNode($dom, $tot, 'MontoGravadoI1', self::fmt($neto));
        self::addNode($dom, $tot, 'ITBIS1', self::fmt($totalI));
        self::addNode($dom, $tot, 'TotalITBIS', self::fmt($totalI));
        self::addNode($dom, $tot, 'TotalImpuestosAdicionales', '0.00');
        self::addNode($dom, $tot, 'MontoTotal', self::fmt($d['total_neto']));
    }

    // -----------------------------------------------------------------------

    private static function addNode(\DOMDocument $dom, \DOMElement $parent, string $tag, string $value): void
    {
        if ($value === '' || $value === null) {
            return; // DGII rechaza nodos vacíos
        }
        $node = $dom->createElement($tag, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($node);
    }

    private static function fmt(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
