<?php
/**
 * Clase para parsear XML de e-CF (Comprobante Fiscal Electrónico).
 */

namespace FacturaScripts\Plugins\eCF_GMV\Lib\DGII;

class DgiiXmlParser
{
    /**
     * Parsea un XML de e-CF y devuelve un array con los datos estructurados.
     *
     * @param string $xml
     * @return array
     * @throws \Exception
     */
    public static function parse(string $xml): array
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml)) {
            throw new \Exception("No se pudo cargar el XML.");
        }

        $xpath = new \DOMXpath($dom);
        
        // Registrar namespace si existe
        $rootNamespace = $dom->documentElement->namespaceURI;
        if ($rootNamespace) {
            $xpath->registerNamespace('ns', $rootNamespace);
            $p = 'ns:';
        } else {
            $p = '';
        }

        $data = [
            'rnc_emisor'    => self::val($xpath, "//{$p}Encabezado/{$p}Emisor/{$p}RNCEmisor"),
            'nombre_emisor' => self::val($xpath, "//{$p}Encabezado/{$p}Emisor/{$p}RazonSocialEmisor"),
            'encf'          => self::val($xpath, "//{$p}Encabezado/{$p}IdDoc/{$p}eNCF"),
            'fecha_emision' => self::val($xpath, "//{$p}Encabezado/{$p}IdDoc/{$p}FechaEmision"),
            'monto_total'   => (float)self::val($xpath, "//{$p}Encabezado/{$p}Totales/{$p}MontoTotal"),
            'total_itbis'   => (float)self::val($xpath, "//{$p}Encabezado/{$p}Totales/{$p}TotalITBIS"),
            'items'         => []
        ];

        $items = $xpath->query("//{$p}DetallesItems/{$p}Item");
        foreach ($items as $itemNode) {
            $data['items'][] = [
                'descripcion' => self::val($xpath, ".//{$p}NombreItem", $itemNode),
                'cantidad'    => (float)self::val($xpath, ".//{$p}CantidadItem", $itemNode),
                'precio'      => (float)self::val($xpath, ".//{$p}PrecioUnitarioItem", $itemNode),
                'total'       => (float)self::val($xpath, ".//{$p}MontoItem", $itemNode),
                'itbis'       => (float)self::val($xpath, ".//{$p}MontoITBIS", $itemNode),
            ];
        }

        return $data;
    }

    private static function val(\DOMXpath $xpath, string $query, \DOMNode $context = null): string
    {
        $node = $xpath->query($query, $context);
        return $node->length > 0 ? trim($node->item(0)->nodeValue) : '';
    }
}
