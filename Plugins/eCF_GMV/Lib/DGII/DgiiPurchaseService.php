<?php
/**
 * Servicio para procesar compras automáticas a partir de e-CF.
 */

namespace FacturaScripts\Plugins\eCF_GMV\Lib\DGII;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\LineaFacturaProveedor;

class DgiiPurchaseService
{
    /**
     * Procesa los datos de un e-CF y crea la factura de proveedor.
     *
     * @param array $data Datos parseados por DgiiXmlParser
     * @return int|null ID de la factura creada o null si falla
     */
    public static function createPurchase(array $data): ?int
    {
        if (empty($data['rnc_emisor']) || empty($data['encf'])) {
            return null;
        }

        // 1. Buscar o crear el proveedor
        $proveedor = self::findOrCreateProveedor($data['rnc_emisor'], $data['nombre_emisor']);
        if (!$proveedor) {
            return null;
        }

        // 2. Verificar si la factura ya existe (Evitar duplicados)
        $facturaExistente = new FacturaProveedor();
        if ($facturaExistente->loadFromCode($data['encf'], 'codigo')) {
            Tools::log()->warning("La factura de compra {$data['encf']} ya existe.");
            return (int)$facturaExistente->idfactura;
        }

        // 3. Crear la factura de proveedor
        $factura = new FacturaProveedor();
        $factura->idproveedor = $proveedor->idproveedor;
        $factura->codproveedor = $proveedor->codproveedor;
        $factura->nombre = $proveedor->nombre;
        $factura->cifnif = $proveedor->cifnif;
        $factura->fecha = date('Y-m-d', strtotime($data['fecha_emision']));
        $factura->fechavencimiento = $factura->fecha;
        $factura->codigo = $data['encf']; // Usamos el e-NCF como código de factura
        $factura->numero = $data['encf'];
        $factura->observaciones = "Generada automáticamente desde e-CF (B2B)";
        
        if (!$factura->save()) {
            Tools::log()->error("Error al guardar la factura de proveedor {$data['encf']}.");
            return null;
        }

        // 4. Agregar líneas
        foreach ($data['items'] as $item) {
            $linea = new LineaFacturaProveedor();
            $linea->idfactura = $factura->idfactura;
            $linea->descripcion = $item['descripcion'];
            $linea->cantidad = $item['cantidad'];
            $linea->pvpunitario = $item['precio'];
            $linea->pvptotal = $item['total'];
            
            // Si hay ITBIS, intentar asignar el impuesto correcto
            if ($item['itbis'] > 0) {
                $linea->iva = 18; // Valor por defecto en RD
            } else {
                $linea->iva = 0;
            }

            if (!$linea->save()) {
                Tools::log()->error("Error al guardar línea '{$item['descripcion']}' de la factura {$data['encf']}.");
            }
        }

        // Recalcular totales para asegurar consistencia
        $factura->recalculate();
        $factura->save();

        return (int)$factura->idfactura;
    }

    private static function findOrCreateProveedor(string $rnc, string $nombre): ?Proveedor
    {
        $proveedor = new Proveedor();
        
        // Intentar buscar por RNC
        $items = $proveedor->all([['cifnif', '=', $rnc]]);
        if (!empty($items)) {
            return $items[0];
        }

        // Si no existe, crearlo
        $nuevo = new Proveedor();
        $nuevo->cifnif = $rnc;
        $nuevo->nombre = $nombre ?: "Proveedor RNC $rnc";
        $nuevo->activo = true;
        
        if ($nuevo->save()) {
            Tools::log()->notice("Nuevo proveedor creado automáticamente: $nombre ($rnc)");
            return $nuevo;
        }

        Tools::log()->error("No se pudo crear el proveedor $rnc.");
        return null;
    }
}
