<?php

namespace FacturaScripts\Plugins\eCF_GMV\Lib;

use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Plugins\eCF_GMV\Model\ECFConfiguracion;

class ECFPdfGenerator
{
    /**
     * Genera el contenido para el código QR obligatorio en la representación impresa.
     * 
     * @param FacturaCliente $factura
     * @param string $signatureValue Valor de la firma digital (SignatureValue del XML)
     * @return string URL completa para el QR
     */
    public static function getQrData(FacturaCliente $factura, string $signatureValue): string
    {
        $config = (new ECFConfiguracion())->getConfiguracion();
        
        // El Código de Seguridad son los primeros 6 caracteres del SignatureValue
        $codSeguridad = substr($signatureValue, 0, 6);
        
        $urlBase = ($config->ambiente === 'eCF') 
            ? 'https://ecf.dgii.gov.do/consultaeCF/VerificarFactura' 
            : 'https://testecf.dgii.gov.do/consultaeCF/VerificarFactura';

        // Intentar obtener el RNC del comprador (si existe)
        $rncComprador = '';
        if ($factura->getCliente()) {
            $rncComprador = $factura->getCliente()->cifnif;
        }

        $params = [
            'RncEmisor'          => $config->rnc_emisor,
            'RncComprador'       => $rncComprador ?: '000000000',
            'eNCF'               => $factura->ncf,
            'MontoTotal'         => number_format($factura->total, 2, '.', ''),
            'FechaEmision'       => date('d-m-Y', strtotime($factura->fecha)),
            'FechaFirma'         => date('d-m-Y H:i:s'),
            'CodigoSeguridadeCF' => $codSeguridad
        ];

        return $urlBase . '?' . http_build_query($params);
    }

    /**
     * Obtiene el código de seguridad de 6 caracteres.
     */
    public static function getCodigoSeguridad(string $signatureValue): string
    {
        return substr($signatureValue, 0, 6);
    }
}
