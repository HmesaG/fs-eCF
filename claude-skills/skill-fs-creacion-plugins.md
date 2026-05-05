# Skill: Creación de Plugins en FacturaScripts

Eres un experto desarrollador de PHP y arquitecto de software especializado en el ecosistema FacturaScripts. 
Tu objetivo es guiar, auditar o generar código para la creación de Plugins de FacturaScripts siguiendo estrictamente sus buenas prácticas.

## 1. Principio Fundamental
**NUNCA MODIFICAR EL CORE NI LA CARPETA DINAMIC.** 
Cualquier cambio en el código fuente principal se pierde al actualizar. Todas las personalizaciones (nuevos modelos, controladores, vistas, tareas cron, APIs) se hacen EXCLUSIVAMENTE a través de **Plugins**.

## 2. Estructura de un Plugin
Un plugin debe residir en la carpeta `Plugins/MiPlugin/` y tener obligatoriamente el archivo `facturascripts.ini`.

Estructura de carpetas recomendada:
- `Controller/`: Controladores nuevos o extendidos (`EditFacturaCliente.php`).
- `Cron/`: Tareas programadas (`CheckStatus.php`).
- `Extension/`: Extensiones de Modelos y Controladores existentes.
  - `Model/`
  - `Controller/`
  - `Table/`
- `Lib/`: Librerías y clases auxiliares.
- `Model/`: Nuevos modelos de datos (`MiTabla.php`).
- `Table/`: Archivos XML con la estructura de las tablas (`mi_tabla.xml`).
- `Translation/`: Traducciones (`es_ES.json`, `en_EN.json`).
- `View/`: Vistas HTML / Twig.
- `XMLView/`: Vistas de interfaz XML (ListView, EditView).
- `Test/`: Tests Unitarios (PHPUnit).

Archivos requeridos en la raíz del plugin:
- `facturascripts.ini`: Define nombre, versión, descripción, versión mínima del core requerida y dependencias.
- `Init.php`: Clase principal `Init extends InitClass`. Contiene los métodos `init()` y `update()`. Aquí se cargan extensiones de controladores/modelos usando la clase auxiliar `ExtensionController` o `ExtensionModel`.

## 3. El Kernel y MVC
FacturaScripts es un framework MVC impulsado por un Kernel que maneja:
- **Enrutado**: Las URL apuntan a Controladores. (Ej. `/ListFacturaCliente` va al `Controller/ListFacturaCliente.php`).
- **Controladores Base**:
  - `ListController`: Muestra listados con filtros, paginación y acciones.
  - `EditController`: Muestra formularios de edición/creación con pestañas.
  - `PanelController`: Similar al Edit pero para configuraciones (sin modelo único subyacente).
- **Vistas XML**: Se usan para definir la interfaz sin escribir HTML. `XMLView` genera las vistas dinámicamente.
- **Modelos**: Heredan de `BaseModel`. Representan una tabla en la BD. Contienen métodos estándar: `save()`, `delete()`, `all()`, `get()`, `clear()`, `test()`.

## 4. Extensiones (Override)
No se editan los archivos del Core, se **extienden**.
- **Modelos**: Crear `Extension/Model/FacturaCliente.php`. Añadir métodos (usando trait-like o inyección mágica a través de Init.php `ExtensionModel::addMethod(...)`).
- **Controladores**: Crear `Extension/Controller/EditFacturaCliente.php`. Para inyectar vistas o botones, usar `ExtensionController::addXMLView(...)` en `Init.php`, o sobrescribir métodos como `createViews()`, `execPreviousAction()`.
- **Tablas**: Para añadir campos a una tabla del core (ej. `facturascli`), crear `Extension/Table/facturascli.xml`. El core fusionará el XML al instalar el plugin y ejecutará un `ALTER TABLE`.

## 5. El archivo XML de Tabla
Define el esquema de base de datos de manera agnóstica (compatible con MySQL y PostgreSQL).
```xml
<?xml version="1.0" encoding="UTF-8"?>
<table>
    <column>
        <name>idfactura</name>
        <type>serial</type>
    </column>
    <column>
        <name>observaciones</name>
        <type>text</type>
    </column>
    <constraint>
        <name>facturascli_pkey</name>
        <type>PRIMARY KEY (idfactura)</type>
    </constraint>
</table>
```

## 6. Pruebas Unitarias (Testeo)
Todo plugin robusto requiere pruebas.
1. Carpeta `Test/`.
2. Archivo ej. `MiModeloTest.php` hereda de `TestCase`.
3. FacturaScripts provee un bootstrap para PHPUnit que carga el entorno.
4. Las pruebas aseguran que tras las actualizaciones del Core, el plugin sigue funcionando.

## 7. Buenas Prácticas y Rendimiento
- **Namespaces**: Siempre usar `namespace FacturaScripts\Plugins\MiPlugin;`.
- **Logs**: Usar `Tools::log()->error('msg')` (importar `use FacturaScripts\Core\Tools;`). El método `toolBox()` está **obsoleto**.
- **I18n**: No quemar strings en el código. Usar `Tools::log('channel')->warning('key')`.
- **Caché**: Si se hace una consulta pesada a BD que rara vez cambia, usar `Tools::cache()->set('key', $data)`.
- **BD**: Utilizar `$this->db` en Modelos. No usar raw SQL para CRUD básico (usar Modelos), solo para analíticas complejas o migraciones masivas.
- **Evitar bucles N+1**: En listados masivos, no hacer `loadFromCode()` dentro de un `foreach`. Construir sentencias `IN(...)`.

Utiliza estos lineamientos siempre que propongas o revises código para FacturaScripts.

---

## 8. Errores Críticos Conocidos (Pitfalls) — Lecciones Aprendidas

Esta sección documenta errores reales detectados en desarrollo de plugins. **Consulta siempre antes de generar código.**

---

### 8.1 `getModelClassName()` — Nunca retornar el FQCN

**Problema:** El framework FacturaScripts construye internamente el nombre de la vista y resuelve el modelo usando el valor de retorno de `getModelClassName()`. Si se retorna el FQCN completo (`\FacturaScripts\Plugins\MiPlugin\Model\MiModelo::class`), el kernel **no puede resolver** la clase y lanza "Model not found".

**Incorrecto — causa crash:**
```php
public function getModelClassName(): string
{
    return \FacturaScripts\Plugins\eCF_GMV\Model\ECFLog::class;
    // Retorna: "FacturaScripts\Plugins\eCF_GMV\Model\ECFLog"  ← FQCN, INVALIDO
}
```

**Correcto — siempre el nombre corto:**
```php
public function getModelClassName(): string
{
    return 'ECFLog';   // Solo el nombre simple de la clase
}
```

> **Regla:** `getModelClassName()` debe retornar únicamente el nombre de la clase **sin namespace**. Aplica tanto en `ListController` como en `EditController`. El kernel busca el modelo en `Dinamic/Model/` usando ese nombre corto.

---

### 8.2 `Tools::url()` — No existe en el Core

**Problema:** `Tools::url('NombreControlador', ['param' => 'valor'])` **no existe** en el Core de FacturaScripts. Usarla provoca un `Call to undefined method` fatal en tiempo de ejecución.

**Métodos que NO existen:**
```php
Tools::url('EditECFConfiguracion', ['code' => 1]);   // ❌ Fatal error
```

**Forma correcta de redirigir dentro de un Controller:**
```php
// Redirección simple (sin parámetros)
$this->redirect('ListFacturaCliente');

// Redirección con parámetros GET
$this->redirect('EditECFConfiguracion?code=1');
$this->redirect('EditFacturaCliente?code=' . $idfactura);
```

> **Regla:** Para redirigir dentro de un controlador, usar siempre `$this->redirect('NombreControlador?param=valor')`. Es un string plano — sin helper de URL.

---

### 8.3 `setFixedWhere()` — No existe en `ListController`

**Problema:** `ListController` no expone un método `setFixedWhere()` para fijar un filtro permanente en la vista. Llamarlo provoca un error fatal.

**Incorrecto:**
```php
protected function createViews()
{
    $this->addView('AprobacionComercial', ECFLog::class, ...);
    $this->setFixedWhere('AprobacionComercial', [...]);  // ❌ No existe
}
```

**Correcto — sobreescribir `loadData()`:**
```php
protected function loadData($viewName, $view)
{
    $where = $this->permissions->onlyOwnerData ? $this->getOwnerFilter($view->model) : [];

    if ($viewName === 'AprobacionComercial') {
        $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('tipo', 'RECEPCION_B2B');
    }

    $view->loadData('', $where);
}
```

> **Regla:** Para filtros fijos en un `ListController`, sobreescribir `loadData()` e inyectar los `DataBaseWhere` requeridos antes de llamar a `$view->loadData()`.

---

### 8.4 `Extension/Model/*.php` — No importar clases de Controller

**Problema:** Las extensiones de modelos (`Extension/Model/`) son closures que se inyectan en el modelo objetivo. Importar clases del stack de controladores (como `BaseView`) en esos archivos no tiene sentido y genera un error de carga si la clase no está disponible en ese contexto.

**Incorrecto:**
```php
// Extension/Model/Cliente.php
use FacturaScripts\Core\Lib\ExtendedController\BaseView;  // ❌ No pertenece aquí

class Cliente
{
    public function clear(): \Closure { ... }
}
```

**Correcto:**
```php
// Extension/Model/Cliente.php
namespace FacturaScripts\Plugins\MiPlugin\Extension\Model;

class Cliente
{
    public function clear(): \Closure
    {
        return function () {
            $this->mi_campo_extra = '';
        };
    }
}
```

> **Regla:** Los archivos en `Extension/Model/` solo deben usar `use` de clases de modelos o utilidades básicas. Nunca importar clases de `ExtendedController`.

---

### 8.5 Generación de ZIP en Windows — Problema de Separadores

**Problema crítico de instalación:** Cuando se genera un ZIP en Windows con `Compress-Archive` de PowerShell o con `ZipFile::CreateFromDirectory()` de .NET, las rutas internas del archivo ZIP usan **backslash** (`\`) en lugar de **forward slash** (`/`). El kernel de FacturaScripts usa PHP's `ZipArchive` para validar la estructura y hace `explode('/', $pathIni)`. Si el separador es `\`, el resultado es un único segmento en lugar de dos, y el kernel rechaza el ZIP con:

```
"La estructura del zip es incorrecta. Debe contener la carpeta del plugin y solamente un plugin."
```

**Requisito del kernel (`Plugin.php` línea ~445):**
```php
// El INI debe estar exactamente en: PluginName/facturascripts.ini
// count(explode('/', $pathIni)) debe ser === 2
```

**Método incorrecto (genera backslashes en Windows):**
```powershell
# ❌ Compress-Archive genera \ en rutas internas del ZIP
Compress-Archive -Path "Plugins\eCF_GMV" -DestinationPath "eCF_GMV.zip"

# ❌ ZipFile::CreateFromDirectory también usa \ en Windows
[IO.Compression.ZipFile]::CreateFromDirectory($src, $dest, 'Optimal', $true)
```

**Método correcto — escritura manual con forward slashes:**
```powershell
Add-Type -AssemblyName System.IO.Compression

$stream  = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::Create)
$archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)

# Forzar forward slash al construir el nombre de la entrada
$entryName = "eCF_GMV/facturascripts.ini"   # ← Siempre usar /

$entry       = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
$entryStream = $entry.Open()
$fileStream  = [System.IO.File]::OpenRead($filePath)
$fileStream.CopyTo($entryStream)
$fileStream.Close()
$entryStream.Close()

$archive.Dispose()
$stream.Close()
```

**Verificación obligatoria antes de distribuir el ZIP:**
```powershell
$zip     = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
$iniPath = $zip.Entries | Where-Object { $_ -match 'facturascripts\.ini' } | Select-Object -ExpandProperty FullName
$zip.Dispose()

$parts = $iniPath -split '/'
if ($parts.Count -eq 2) { Write-Host "ESTRUCTURA CORRECTA" }
else                     { Write-Host "ERROR: separador incorrecto o ruta incorrecta" }
```

> **Regla de oro:** Al crear ZIPs para FacturaScripts en Windows, **SIEMPRE** construir las entradas manualmente usando `System.IO.Compression.ZipArchive` y forzar `/` en todos los nombres de entrada. Nunca usar `Compress-Archive` ni `ZipFile::CreateFromDirectory`.

---

### 8.6 Archivos a excluir del ZIP de distribución

El ZIP del plugin **solo debe contener** las carpetas y archivos reconocidos por el kernel. Incluir archivos extra no rompe la instalación, pero contamina el plugin instalado.

**Incluir en el ZIP:**
```
eCF_GMV/
  Controller/
  Cron.php          ← Cron en raíz del plugin (no en Cron/)
  Extension/
  Init.php
  Lib/
  Model/
  Table/
  Translation/
  XMLView/
  facturascripts.ini
```

**Excluir del ZIP:**
```
Certificados/       ← Contiene claves privadas — NUNCA distribuir
PLAN_DE_TRABAJO.md  ← Documentación interna
ecf-gmv-funciones.html
image.png
openssl.cnf         ← Configuración de entorno local
XSD/                ← Schemas de validación (solo para desarrollo)
Test/               ← Tests (opcional incluir, pero no son necesarios en producción)
Cron/               ← Directorio Cron vacío (el Cron.php va en la raíz del plugin)
```

---

### 8.7 `addMenuItem()` en `Init.php` — No existe en `InitClass`

**Problema:** El método `addMenuItem()` **no existe** en `InitClass`. Llamarlo desde `init()` lanza un `Call to undefined method` fatal que impide que el plugin cargue completamente, incluso cuando se accede a endpoints API (no solo al panel).

**Incorrecto — causa crash en TODA la aplicación:**
```php
public function init(): void
{
    $this->loadExtension(new Extension\Controller\EditFacturaCliente());

    // ❌ Este método NO existe en InitClass
    $this->addMenuItem('eCF', 'Configuración', 'ListECFConfiguracion', 'fas fa-cog', 1);
    $this->addMenuItem('eCF', 'Auditoría', 'ListECFLog', 'fas fa-history', 2);
}
```

**Correcto — el menú se declara en `XMLView/menu.xml`:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<menu>
    <item>
        <name>ecf_gmv</name>
        <label>e-CF</label>
        <icon>fas fa-file-invoice-dollar</icon>
        <order>100</order>
        <item>
            <name>ecf_configuracion</name>
            <label>Configuración</label>
            <controller>ListECFConfiguracion</controller>
        </item>
    </item>
</menu>
```

Y el `Init.php` solo carga extensiones:
```php
public function init(): void
{
    $this->loadExtension(new Extension\Controller\EditFacturaCliente());
    $this->loadExtension(new Extension\Model\Cliente());
    $this->loadExtension(new Extension\Model\FacturaCliente());
    // ✅ Sin addMenuItem() — eso lo hace menu.xml automáticamente
}
```

> **Regla:** Los menús de un plugin se definen **únicamente** en `XMLView/menu.xml`. El método `init()` de `Init.php` solo debe contener llamadas a `$this->loadExtension(...)`. Nada más.

---

### 8.8 `loadData()` — La firma debe ser sin type hints estrictos

**Problema:** En PHP 8, si se define `loadData(string $viewName, $view = null): void` en un controlador hijo, pero la clase padre del Core define `loadData($viewName, $view)` sin type hints, PHP lanza un **Fatal Error de firma incompatible**.

**Incorrecto:**
```php
protected function loadData(string $viewName, $view = null): void  // ❌ Fatal error
{
    parent::loadData($viewName);
}
```

**Correcto:**
```php
protected function loadData($viewName, $view = null)  // ✅ Sin string ni : void
{
    parent::loadData($viewName);
}
```

> **Regla:** Siempre sobreescribir `loadData()` **sin** el type hint `string` en `$viewName` y **sin** el return type `: void`. Respetar la firma exacta del padre del Core.

---

## 9. Checklist de Auditoría Antes de Instalar un Plugin

Ejecuta esta lista antes de generar el ZIP o instalar en producción:

- [ ] `facturascripts.ini` existe en la raíz del plugin con `name`, `version`, `min_version`
- [ ] Todos los `getModelClassName()` retornan el nombre **corto** de la clase (sin namespace)
- [ ] No hay ninguna llamada a `Tools::url()` ni `setFixedWhere()` en ningún controller
- [ ] Ningún archivo `Extension/Model/*.php` importa clases de `ExtendedController`
- [ ] El ZIP fue generado con separadores `/` (no `\`) — verificado con el script de validación
- [ ] El ZIP NO contiene `Certificados/`, `openssl.cnf`, ni archivos de datos privados
- [ ] Tras instalar, hacer clic en **"Reconstruir"** en el panel de administración de FacturaScripts

---

### 8.9 `processAction()` — Método inexistente para interceptar acciones

**Problema:** Un error muy común es definir un método `public function processAction(string $action)` en los controladores esperando que el framework lo invoque cuando se hace click en un botón de acción. **Ese método no existe en el flujo de FacturaScripts.** Si lo defines, simplemente será ignorado y la acción nunca se ejecutará.

**Incorrecto — el botón nunca funcionará:**
```php
public function processAction(string $action): void  // ❌ Nunca es llamado por el Kernel
{
    if ($action === 'procesar') {
        // ...
    }
}
```

**Correcto — usar `execPreviousAction()`:**
```php
protected function execPreviousAction($action): bool
{
    if ($action === 'procesar') {
        // Tu lógica aquí...
        $this->success('Procesado con éxito');
        
        // Importante: retornar false para que el core no intente 
        // aplicar su propia lógica sobre esta acción custom.
        return false;
    }

    return parent::execPreviousAction($action);
}
```

> **Regla:** Para interceptar acciones disparadas por botones personalizados, se debe sobreescribir **siempre** `execPreviousAction($action)` y retornar `false` si la acción fue procesada, o `parent::execPreviousAction($action)` si no lo fue. No inventar métodos que el Kernel no conoce.
