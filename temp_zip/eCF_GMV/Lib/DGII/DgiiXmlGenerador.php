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
     * Genera el XML de Acuse de Recibo Electrónico (ARECF).
     */
    public static function generarARECF(array $datos): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElement('ARECF');
        $dom->appendChild($root);

        $det = $dom->createElement('DetalleAcusedeRecibo');
        $root->appendChild($det);

        self::addNode($dom, $det, 'Version', '1.0');
        self::addNode($dom, $det, 'RNCEmisor', $datos['rnc_emisor']);
        self::addNode($dom, $det, 'RNCComprador', $datos['rnc_comprador']);
        self::addNode($dom, $det, 'eNCF', $datos['encf']);
        self::addNode($dom, $det, 'Estado', (string)$datos['estado']);
        if (isset($datos['motivo'])) {
            self::addNode($dom, $det, 'CodigoMotivoNoRecibido', (string)$datos['motivo']);
        }
        self::addNode($dom, $det, 'FechaHoraAcuseRecibo', date('d-m-Y H:i:s'));

        self::limpiarNodosVacios($dom);
        $xml = $dom->saveXML();
        self::validarConXSD($xml, 'ARECF');
        return $xml;
    }

    /**
     * Genera el XML de Aprobación Comercial (ACECF).
     */
    public static function generarACECF(array $datos): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElement('ACECF');
        $dom->appendChild($root);

        $det = $dom->createElement('DetalleAprobacionComercial');
        $root->appendChild($det);

        self::addNode($dom, $det, 'Version', '1.0');
        self::addNode($dom, $det, 'RNCEmisor', $datos['rnc_emisor']);
        self::addNode($dom, $det, 'eNCF', $datos['encf']);
        self::addNode($dom, $det, 'FechaEmision', date('d-m-Y', strtotime($datos['fecha_emision'])));
        self::addNode($dom, $det, 'MontoTotal', self::fmt((float)$datos['monto_total']));
        self::addNode($dom, $det, 'RNCComprador', $datos['rnc_comprador']);
        self::addNode($dom, $det, 'Estado', (string)$datos['estado']);
        if (isset($datos['motivo_rechazo'])) {
            self::addNode($dom, $det, 'DetalleMotivoRechazo', $datos['motivo_rechazo']);
        }
        self::addNode($dom, $det, 'FechaHoraAprobacionComercial', date('d-m-Y H:i:s'));

        self::limpiarNodosVacios($dom);
        $xml = $dom->saveXML();
        self::validarConXSD($xml, 'ACECF');
        return $xml;
    }

    /**
     * Genera el XML de Reporte de Consumo Electrónico (RFCE).
     * Nota: Usado para facturas tipo 32 menores al umbral de 250k.
     */
    public static function generarRFCE(array $datos): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElement('RFCE');
        $dom->appendChild($root);

        $enc = $dom->createElement('Encabezado');
        $root->appendChild($enc);

        self::addNode($dom, $enc, 'Version', '1.0');

        $idDoc = $dom->createElement('IdDoc');
        $enc->appendChild($idDoc);
        self::addNode($dom, $idDoc, 'TipoeCF', '32');
        self::addNode($dom, $idDoc, 'eNCF', $datos['encf']);
        self::addNode($dom, $idDoc, 'TipoIngresos', $datos['tipo_ingreso'] ?? '01');
        self::addNode($dom, $idDoc, 'TipoPago', $datos['tipo_pago'] ?? '1');

        $emisor = $dom->createElement('Emisor');
        $enc->appendChild($emisor);
        self::addNode($dom, $emisor, 'RNCEmisor', $datos['emisor_rnc']);
        self::addNode($dom, $emisor, 'RazonSocialEmisor', $datos['emisor_nombre']);
        self::addNode($dom, $emisor, 'FechaEmision', date('d-m-Y', strtotime($datos['fecha_emision'])));

        $comp = $dom->createElement('Comprador');
        $enc->appendChild($comp);
        if (!empty($datos['comprador_rnc'])) {
            self::addNode($dom, $comp, 'RNCComprador', $datos['comprador_rnc']);
        }
        if (!empty($datos['comprador_nombre'])) {
            self::addNode($dom, $comp, 'RazonSocialComprador', $datos['comprador_nombre']);
        }

        $tot = $dom->createElement('Totales');
        $enc->appendChild($tot);
        $neto  = (float)$datos['total_neto'] - (float)$datos['total_itbis'];
        $itbis = (float)$datos['total_itbis'];

        self::addNode($dom, $tot, 'MontoGravadoTotal', self::fmt($neto));
        self::addNode($dom, $tot, 'MontoGravadoI1', self::fmt($neto));
        self::addNode($dom, $tot, 'TotalITBIS', self::fmt($itbis));
        self::addNode($dom, $tot, 'TotalITBIS1', self::fmt($itbis));
        self::addNode($dom, $tot, 'MontoTotal', self::fmt((float)$datos['total_neto']));

        self::addNode($dom, $enc, 'CodigoSeguridadeCF', $datos['codigo_seguridad'] ?? substr(md5($datos['encf']), 0, 6));

        self::limpiarNodosVacios($dom);
        $xml = $dom->saveXML();
        self::validarConXSD($xml, 'RFCE');
        return $xml;
    }


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

        self::limpiarNodosVacios($dom);
        $xml = $dom->saveXML();

        // Validar contra XSD antes de retornar (Fase 2)
        self::validarConXSD($xml, (string)$datos['tipoecf']);

        return $xml;
    }

    /**
     * Valida un XML contra su esquema XSD oficial.
     *
     * @param string $xml
     * @param string $tipo Tipo de e-CF (31, 32, etc.) o nombre de proceso (ARECF, ACECF)
     * @throws \Exception
     */
    public static function validarConXSD(string $xml, string $tipo): void
    {
        $libxml_state = libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml)) {
            $error = libxml_get_last_error();
            libxml_use_internal_errors($libxml_state);
            throw new \Exception("Error cargando XML: " . ($error ? $error->message : 'Desconocido'));
        }

        $filename = self::getXsdFile($tipo);
        if (!$filename) {
            libxml_use_internal_errors($libxml_state);
            return; // No hay XSD definido para este tipo
        }

        // Buscar en la carpeta XSD dentro del plugin
        $xsdPath = realpath(__DIR__ . '/../../XSD/' . $filename);
        
        if (!$xsdPath || !file_exists($xsdPath)) {
            libxml_use_internal_errors($libxml_state);
            throw new \Exception("No se encontró el archivo XSD: $xsdPath");
        }

        if (!$dom->schemaValidate($xsdPath)) {
            $errors = libxml_get_errors();
            $msg = "Error de validación XSD ($filename):";
            foreach ($errors as $error) {
                $msg .= sprintf("\n[Línea %d] %s", $error->line, trim($error->message));
            }
            libxml_clear_errors();
            libxml_use_internal_errors($libxml_state);
            throw new \Exception($msg);
        }

        libxml_use_internal_errors($libxml_state);
    }

    private static function getXsdFile(string $tipo): string
    {
        $map = [
            '31'    => 'e-CF 31 v.1.0.xsd',
            '32'    => 'e-CF 32 v.1.0.xsd',
            '33'    => 'e-CF 33 v.1.0.xsd',
            '34'    => 'e-CF 34 v.1.0.xsd',
            '41'    => 'e-CF 41 v.1.0.xsd',
            '43'    => 'e-CF 43 v.1.0.xsd',
            '44'    => 'e-CF 44 v.1.0.xsd',
            '45'    => 'e-CF 45 v.1.0.xsd',
            '46'    => 'e-CF 46 v.1.0.xsd',
            '47'    => 'e-CF 47 v.1.0.xsd',
            'ARECF' => 'ARECF v1.0.xsd',
            'ACECF' => 'ACECF v.1.0.xsd',
            'ANECF' => 'ANECF v.1.0.xsd',
            'RFCE'  => 'RFCE 32 v.1.0.xsd'
        ];

        return $map[$tipo] ?? '';
    }

    // -----------------------------------------------------------------------

    private static function agregarEncabezado(\DOMDocument $dom, \DOMElement $root, array $d): void
    {
        $enc = $dom->createElement('Encabezado');
        $root->appendChild($enc);

        self::addNode($dom, $enc, 'Version', '1.0');
        self::addNode($dom, $enc, 'TipoeCF', $d['tipoecf']);
        self::addNode($dom, $enc, 'eNCF', $d['encf']);
        self::addNode($dom, $enc, 'FechaVencimientoSecuencia', date('d-m-Y', strtotime($d['fecha_venc_seq'])));
        
        // Indicadores de Nota de Crédito/Débito
        if ($d['tipoecf'] === '34') {
            self::addNode($dom, $enc, 'IndicadorNotaCredito', $d['indicador_nc'] ?? '1');
        } elseif ($d['tipoecf'] === '33') {
            self::addNode($dom, $enc, 'IndicadorNotaDebito', $d['indicador_nd'] ?? '1');
        }

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

        // Comprador (obligatorio para 31, 33, 34, 41, 43, 44, 45, 47)
        if (!empty($d['comprador_rnc'])) {
            $comp = $dom->createElement('Comprador');
            $enc->appendChild($comp);
            self::addNode($dom, $comp, 'RNCComprador', $d['comprador_rnc']);
            self::addNode($dom, $comp, 'RazonSocialComprador', $d['comprador_nombre']);
            if (!empty($d['comprador_direccion'])) {
                self::addNode($dom, $comp, 'DireccionComprador', $d['comprador_direccion']);
            }
        }

        // Fecha
        self::addNode($dom, $enc, 'FechaEmision', date('d-m-Y', strtotime($d['fecha_emision'])));
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
            // No agregamos TablaSubDescuento si está vacío
            if (!empty($item['descuento']) && $item['descuento'] > 0) {
                $desc = $dom->createElement('TablaSubDescuento');
                $linea->appendChild($desc);
                self::addNode($dom, $desc, 'TipoSubDescuento', '%');
                self::addNode($dom, $desc, 'SubDescuentoPercentage', self::fmt($item['descuento_porcentaje'] ?? 0));
                self::addNode($dom, $desc, 'MontoSubDescuento', self::fmt($item['descuento']));
            }
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
            return;
        }
        $node = $dom->createElement($tag, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($node);
    }

    /**
     * Elimina recursivamente los nodos vacíos del DOM.
     * DGII rechaza XMLs con nodos opcionales vacíos.
     */
    private static function limpiarNodosVacios(\DOMNode $node): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof \DOMElement) {
                self::limpiarNodosVacios($child);
                
                // Si después de limpiar hijos, no tiene hijos ni texto, lo eliminamos
                if (!$child->hasChildNodes() && trim($child->nodeValue) === '') {
                    $node->removeChild($child);
                }
            }
        }
    }

    private static function fmt(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
