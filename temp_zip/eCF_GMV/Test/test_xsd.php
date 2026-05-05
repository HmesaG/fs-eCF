<?php
require_once __DIR__ . '/../../../../Core/Autoload.php';
require_once __DIR__ . '/../../Lib/DGII/DgiiXmlGenerador.php';

use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiXmlGenerador;

try {
    $datos = [
        'rnc_emisor' => '131566332',
        'rnc_comprador' => '101642456',
        'encf' => 'E310000000001',
        'estado' => 1,
        'fecha_emision' => date('Y-m-d'),
        'monto_total' => 100.00
    ];

    echo "Generando ARECF...\n";
    $xml = DgiiXmlGenerador::generarARECF($datos);
    echo "ARECF generado y validado OK.\n";

    echo "Generando ACECF...\n";
    $xml = DgiiXmlGenerador::generarACECF($datos);
    echo "ACECF generado y validado OK.\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
