# 🔍 AUDITORÍA TÉCNICA — Plugin `eCF_GMV`
**Fecha:** 2026-05-04  
**Auditor:** Antigravity AI  
**Referencia:** `skill-fs-creacion-plugins.md`  
**Estado previo:** `REVISION_eCF_GMV_ACTUAL.md` (hallazgos anteriores RESUELTOS)

---

## ✅ Estado de Hallazgos Previos

| Hallazgo anterior | Estado |
|---|---|
| `processAction()` inexistente en `ListResumenConsumo` | ✅ RESUELTO |
| Acceso inseguro a `$this->views[$viewName]` en `ListAprobacionComercial` | ✅ RESUELTO |
| Modelo `ResumenConsumo` con namespace incorrecto | ✅ RESUELTO |
| Propiedad `$total` faltante en `ResumenConsumo` | ✅ RESUELTO |

---

## 🆕 NUEVOS HALLAZGOS

---

### HALLAZGO-01 🔴 CRÍTICO — `EmisorECF.php`: Doble evaluación `$esRFCE` con resultado divergente

**Archivo:** `Lib/EmisorECF.php` — líneas 57 y 76  
**Severidad:** 🔴 CRÍTICO — Bug de lógica silencioso

**Descripción:**

La variable `$esRFCE` se calcula **dos veces** con expresiones ligeramente diferentes:

```php
// Línea 57: compara con (float) cast
$esRFCE = ($factura->tipocomprobante === '32' && (float)$factura->total < self::UMBRAL_RFCE);

// ... se genera y firma el XML con ese valor ...

// Línea 76: compara SIN cast
$esRFCE = ($factura->tipocomprobante === '32' && $factura->total < self::UMBRAL_RFCE);
```

**Riesgo:** Si `$factura->total` es un `string` (como puede devolverlo el ORM de FacturaScripts), la comparación `<` en la línea 76 puede comportarse diferente a la de la línea 57. Podría generarse un XML de tipo RFCE pero luego enviarse por la ruta Normal (o viceversa), provocando rechazo en DGII sin error aparente en el log de PHP.

**Corrección:**

```php
// Eliminar el segundo bloque (líneas 75-79) y conservar solo el primero.
// El enrutamiento debe hacerse con la variable calculada en la línea 57.
$esRFCE = ($factura->tipocomprobante === '32' && (float)$factura->total < self::UMBRAL_RFCE);

$datos        = $this->construirDatosXml($factura);
$xmlSinFirmar = $esRFCE
    ? DgiiXmlGenerador::generarRFCE($datos)
    : DgiiXmlGenerador::generarECF($datos);

// ... firma ...

// Usar la misma variable $esRFCE para el envío
return $esRFCE
    ? $this->enviarRFCE($factura, $xmlFirmado, $xmlSinFirmar)
    : $this->enviarNormal($factura, $xmlFirmado, $xmlSinFirmar);
```

---

### HALLAZGO-02 🔴 CRÍTICO — `DgiiCommercialService.php`: `getConfiguracion()` sin guard de null

**Archivo:** `Lib/DGII/DgiiCommercialService.php` — línea 23-24  
**Severidad:** 🔴 CRÍTICO — Fatal error en producción si no hay configuración

**Descripción:**

```php
$config = (new ECFConfiguracion())->getConfiguracion();
if (!$config->activo) {  // ← $config puede ser null si no hay config guardada
    throw new \Exception("El plugin eCF no está activado.");
}
```

Si `getConfiguracion()` devuelve `null` (configuración no creada todavía), la línea `$config->activo` lanzará un `TypeError: Cannot access property on null`. El try/catch que lo envuelve lo capturará, pero el mensaje de error será confuso para el operador.

**Corrección:**

```php
$config = (new ECFConfiguracion())->getConfiguracion();
if (!$config) {
    throw new \Exception("Plugin eCF-GMV no configurado.");
}
if (!$config->activo) {
    throw new \Exception("El plugin eCF no está activado.");
}
```

---

### HALLAZGO-03 🟠 ALTO — `ECFPdfGenerator.php`: Uso de namespace base en lugar de Dinamic

**Archivo:** `Lib/ECFPdfGenerator.php` — línea 5  
**Severidad:** 🟠 ALTO — Potencial incompatibilidad con modelos extendidos por otros plugins

**Descripción:**

```php
use FacturaScripts\Core\Model\FacturaCliente;  // ❌ Namespace Core
```

El skill de desarrollo establece que en plugins se debe usar `FacturaScripts\Dinamic\Model\...` para que las extensiones de otros plugins (o del propio `eCF_GMV`) sean resueltas correctamente por el autoloader. Usar el namespace `Core` hace que el modelo no incluya los campos extendidos como `ecf_xml_firmado`, `numeroncf`, etc.

Adicionalmente, el método `$factura->ncf` en línea 37 **no existe** en el modelo estándar — el campo correcto es `$factura->numeroncf`.

**Corrección:**

```php
use FacturaScripts\Dinamic\Model\FacturaCliente; // ✅ Dinamic

// Línea 37: 
'eNCF' => $factura->numeroncf, // ✅ nombre correcto del campo
```

---

### HALLAZGO-04 🟠 ALTO — `Extension/Model/Cliente.php`: Método `clear()` sobrescribe sin llamar al padre

**Archivo:** `Extension/Model/Cliente.php`  
**Severidad:** 🟠 ALTO — Pérdida de datos al limpiar el modelo

**Descripción:**

```php
public function clear(): \Closure
{
    return function () {
        $this->url_recepcion_ecf = '';
        // ❌ No llama a parent ni a la cadena de extensiones
    };
}
```

En el sistema de extensiones de FacturaScripts, **el closure debe llamar a `$this->parentClear()`** (o la función anterior en la cadena) para no romper el reseteo de los demás campos del modelo `Cliente`. Si otro plugin también extiende `clear()`, los campos de ese plugin no se resetearán.

**Corrección:**

```php
public function clear(): \Closure
{
    return function () {
        /** @var \FacturaScripts\Dinamic\Model\Cliente $this */
        $this->url_recepcion_ecf = '';
        // Llamar a la cadena anterior si existe
        $this->parentClear();
    };
}
```

> **Nota:** Verificar en el skill/framework si el método de encadenamiento es `parentClear()` o si la extensión debe usar `next($args)`.

---

### HALLAZGO-05 🟠 ALTO — `Cron.php`: FQCN literal para `DgiiResumenServicio::procesarDia()`

**Archivo:** `Cron.php` — línea 43  
**Severidad:** 🟠 ALTO — Anti-patrón de legibilidad y mantenimiento

**Descripción:**

```php
\FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiResumenServicio::procesarDia($ayer);
```

Se usa el Fully Qualified Class Name (FQCN) literal directamente en el cuerpo del método, mientras el resto del archivo usa `use` statements. Esto viola la consistencia del código, dificulta refactors y no genera error, pero es un anti-patrón.

**Corrección:** Agregar el `use` en el encabezado del archivo y usar el nombre corto:

```php
use FacturaScripts\Plugins\eCF_GMV\Lib\DGII\DgiiResumenServicio;

// ...
private function procesarRFCE(): void
{
    $ayer = new \DateTime();
    $ayer->modify('-1 day');
    DgiiResumenServicio::procesarDia($ayer); // ✅
}
```

---

### HALLAZGO-06 🟡 MEDIO — `ListECFConfiguracion.php`: Redireccionamiento en `privateCore()` sin detener ejecución

**Archivo:** `Controller/ListECFConfiguracion.php` — líneas 27-32  
**Severidad:** 🟡 MEDIO — Ejecución innecesaria del ciclo del kernel tras la redirección

**Descripción:**

```php
public function privateCore(&$response, $user, $permissions)
{
    parent::privateCore($response, $user, $permissions);
    $this->redirect('EditECFConfiguracion?code=1');
    // ← La ejecución del framework continúa después de esto
}
```

En FacturaScripts, `$this->redirect()` establece el header de redirección pero **no detiene la ejecución del método**. El kernel continúa procesando `loadData()`, `createViews()`, etc., haciendo trabajo innecesario. Además, si la vista `ListECFConfiguracion` no tiene datos (tabla vacía), esto puede lanzar errores internos antes de que el browser procese el redirect.

**Corrección:**

```php
public function privateCore(&$response, $user, $permissions)
{
    parent::privateCore($response, $user, $permissions);
    $this->redirect('EditECFConfiguracion?code=1');
    return; // ← Detener el método explícitamente
}
```

> **Nota:** Evaluar si `privateCore` en la clase padre de `ListController` permite un early `return` o si se debe sobrescribir de otra forma. Consultar el skill para la forma canónica de redirigir desde un controller.

---

### HALLAZGO-07 🟡 MEDIO — `Extension/Controller/EditFacturaCliente.php`: `execPreviousAction` no retorna `false` en acción interceptada

**Archivo:** `Extension/Controller/EditFacturaCliente.php` — líneas 37-48  
**Severidad:** 🟡 MEDIO — El kernel continúa ejecutando la acción nativa después de la redirección

**Descripción:**

```php
public function execPreviousAction(): Closure
{
    return function (string $action) {
        if ($action === 'imprimir-ecf') {
            $idfactura = $this->request->get('code', '');
            if (!empty($idfactura)) {
                $this->redirect('ImprimirECF?idfactura=' . $idfactura);
            }
            // ❌ No retorna false → el kernel sigue ejecutando
        }
    };
}
```

Según la regla establecida en auditorías previas: cuando una acción personalizada es interceptada y procesada, se debe retornar `false` para indicar al kernel que no ejecute el flujo estándar. El closure debe retornar `false`.

**Corrección:**

```php
return function (string $action): bool {
    if ($action === 'imprimir-ecf') {
        $idfactura = $this->request->get('code', '');
        if (!empty($idfactura)) {
            $this->redirect('ImprimirECF?idfactura=' . $idfactura);
        }
        return false; // ✅ Detener ejecución del kernel
    }
    return true; // Permitir flujo normal para otras acciones
};
```

---

### HALLAZGO-08 🟡 MEDIO — `DgiiApiService.php`: `buildCertHeader()` es un método muerto (dead code)

**Archivo:** `Lib/DGII/DgiiApiService.php` — líneas 262-267  
**Severidad:** 🟡 MEDIO — Dead code, potencial confusión de mantenimiento

**Descripción:**

El método `buildCertHeader(array $certs): string` existe pero **no es llamado en ningún lugar del código** (ni interno ni externo). Sugiere una implementación incompleta o abandonada de autenticación por certificado directo en lugar de JWT.

**Acción recomendada:** Eliminar el método o documentarlo como `@deprecated` con una nota de su propósito futuro.

---

### HALLAZGO-09 🟡 MEDIO — `DgiiCommercialService.php`: Uso de namespace `Core\Model\Cliente` en lugar de `Dinamic`

**Archivo:** `Lib/DGII/DgiiCommercialService.php` — línea 58  
**Severidad:** 🟡 MEDIO — Misma categoría que HALLAZGO-03

**Descripción:**

```php
$cliente = new \FacturaScripts\Core\Model\Cliente(); // ❌
```

Mismo problema que `ECFPdfGenerator.php`. El modelo `Cliente` cargado desde `Core` no incluirá el campo `url_recepcion_ecf` extendido por el propio plugin.

**Corrección:**

```php
$cliente = new \FacturaScripts\Dinamic\Model\Cliente(); // ✅
```

---

### HALLAZGO-10 🔵 INFO — `XMLView/ListResumenConsumo.xml`: Etiqueta `<actions>` puede no ser soportada en `ListController`

**Archivo:** `XMLView/ListResumenConsumo.xml`  
**Severidad:** 🔵 INFO — Posible comportamiento silencioso

**Descripción:**

El bloque `<actions>` con `<params>fecha</params>` asume que el XMLView del `ListController` soporta este tipo de definición de acciones parametrizadas por fila. En el framework estándar de FacturaScripts, los botones de acción en `ListController` se añaden mediante `addButton()` en `createViews()`, no mediante `<actions>` en el XMLView.

Si el motor de XMLView ignora esta sección silenciosamente, el botón "Procesar y Enviar" **nunca se mostrará** en la UI aunque el controlador esté listo para procesarlo.

**Acción recomendada:** Verificar en FacturaScripts 2024+ si `<actions>` en XMLView de `ListController` es funcional. Si no lo es, mover el botón a `createViews()`:

```php
protected function createViews()
{
    $this->addView('ListResumenConsumo', 'ResumenConsumo');
    $this->addButton('ListResumenConsumo', [
        'action' => 'procesar',
        'label'  => 'Procesar y Enviar',
        'icon'   => 'fas fa-paper-plane',
        'color'  => 'primary',
    ]);
}
```

---

## 📊 Resumen Ejecutivo

| # | Severidad | Archivo | Descripción | Estado |
|---|---|---|---|---|
| 01 | 🔴 CRÍTICO | `Lib/EmisorECF.php` | Doble evaluación `$esRFCE` divergente | ✅ RESUELTO |
| 02 | 🔴 CRÍTICO | `Lib/DGII/DgiiCommercialService.php` | Null guard faltante en `getConfiguracion()` | ✅ RESUELTO |
| 03 | 🟠 ALTO | `Lib/ECFPdfGenerator.php` | Namespace `Core` + campo `ncf` incorrecto | ✅ RESUELTO |
| 04 | 🟠 ALTO | `Extension/Model/Cliente.php` | `clear()` sin cadena al método padre | ⚠️ PENDIENTE MANUAL |
| 05 | 🟠 ALTO | `Cron.php` | FQCN literal en lugar de `use` statement | ✅ RESUELTO |
| 06 | 🟡 MEDIO | `Controller/ListECFConfiguracion.php` | Redirect sin `return` | ✅ RESUELTO |
| 07 | 🟡 MEDIO | `Extension/Controller/EditFacturaCliente.php` | `execPreviousAction` no retorna `false` | ✅ RESUELTO |
| 08 | 🟡 MEDIO | `Lib/DGII/DgiiApiService.php` | Dead code `buildCertHeader()` | ✅ RESUELTO (marcado @deprecated) |
| 09 | 🟡 MEDIO | `Lib/DGII/DgiiCommercialService.php` | Namespace `Core\Model\Cliente` | ✅ RESUELTO |
| 10 | 🔵 INFO | `XMLView/ListResumenConsumo.xml` | `<actions>` puede no renderizarse en ListController | ⚠️ VERIFICAR EN RUNTIME |

---

## 🛡️ Verificaciones Positivas (Sin Hallazgos)

| Componente | Resultado |
|---|---|
| `Init.php` — Estructura de tablas (`Table/`) | ✅ Correcto |
| `getModelClassName()` en todos los controllers | ✅ Retorna nombre corto sin FQCN |
| `getPageData()` en todos los controllers | ✅ Llama a `parent::getPageData()` |
| `ListController::createViews()` | ✅ Usa `addView()` correctamente |
| `EditController::createViews()` | ✅ Usa `addEditView()` correctamente |
| `Extension/Table/*.xml` | ✅ Sin redeclaración de columnas de dependencias |
| `Cron.php` — Estructura general y `isTimeForJob` | ✅ Correcto |
| `DgiiApiService` — Manejo de errores HTTP | ✅ Lanza `RuntimeException` consistentemente |
| `menu.xml` — Estructura de menú | ✅ Correcto |
| `ListResumenConsumo::execPreviousAction()` | ✅ Resuelto (auditoría anterior) |
| `ListAprobacionComercial::loadData()` | ✅ Resuelto (auditoría anterior) |
| `ResumenConsumo` — Namespace y propiedades | ✅ Resuelto (auditoría anterior) |

---

*Próxima acción: aplicar correcciones en orden de severidad (CRÍTICO → ALTO → MEDIO)*
