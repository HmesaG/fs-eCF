<?php
/**
 * Firma digital de e-CF usando XMLDSig (RSA-SHA256, enveloped).
 * Requiere extensión PHP: openssl, dom.
 */

namespace FacturaScripts\Plugins\eCF_GMV\Lib\DGII;

class DgiiXmlFirmador
{
    /**
     * Firma un XML de e-CF con el certificado .p12 proporcionado.
     *
     * @param string $xmlString      XML generado (sin firmar)
     * @param string $rutaP12        Ruta absoluta al archivo .p12
     * @param string $passwordP12    Contraseña del .p12
     * @return string XML firmado con nodo <Signature>
     */
    public static function firmarECF(string $xmlString, string $rutaP12, string $passwordP12): string
    {
        // 1. Leer y parsear el .p12
        $p12data = @file_get_contents($rutaP12);
        if ($p12data === false) {
            throw new \RuntimeException('eCF-GMV Firmador: No se puede leer el archivo .p12: ' . $rutaP12);
        }

        $certs = [];
        if (!openssl_pkcs12_read($p12data, $certs, $passwordP12)) {
            throw new \RuntimeException('eCF-GMV Firmador: Contraseña del certificado incorrecta o archivo .p12 inválido.');
        }

        $privateKey  = $certs['pkey'];
        $certificate = $certs['cert'];

        // 2. Cargar el DOM
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = false;
        if (!$dom->loadXML($xmlString)) {
            throw new \RuntimeException('eCF-GMV Firmador: XML de e-CF inválido.');
        }

        // 3. Calcular el digest del contenido a firmar (elemento raíz sin Signature)
        $c14n   = $dom->documentElement->C14N(false, false);
        $digest = base64_encode(hash('sha256', $c14n, true));

        // 4. Construir el SignedInfo canonicalizado
        $signedInfoXml = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">'
            . '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>'
            . '<SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>'
            . '<Reference URI="">'
            . '<Transforms>'
            . '<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>'
            . '<Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>'
            . '</Transforms>'
            . '<DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'
            . '<DigestValue>' . $digest . '</DigestValue>'
            . '</Reference>'
            . '</SignedInfo>';

        $siDom = new \DOMDocument();
        $siDom->loadXML($signedInfoXml);
        $signedInfoC14n = $siDom->documentElement->C14N(false, false);

        // 5. Firmar con la clave privada RSA-SHA256
        $signatureValue = '';
        openssl_sign($signedInfoC14n, $signatureValue, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureB64 = base64_encode($signatureValue);

        // 6. Extraer el certificado público en base64
        $certClean = str_replace(
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"],
            '',
            $certificate
        );

        // 7. Construir el nodo <Signature> completo
        $signatureXml = '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">'
            . $signedInfoXml
            . '<SignatureValue>' . $signatureB64 . '</SignatureValue>'
            . '<KeyInfo>'
            . '<X509Data>'
            . '<X509Certificate>' . trim($certClean) . '</X509Certificate>'
            . '</X509Data>'
            . '</KeyInfo>'
            . '</Signature>';

        // 8. Insertar como último hijo del elemento raíz
        $sigDom = new \DOMDocument();
        $sigDom->loadXML($signatureXml);
        $sigNode = $dom->importNode($sigDom->documentElement, true);
        $dom->documentElement->appendChild($sigNode);

        return $dom->saveXML();
    }
}
