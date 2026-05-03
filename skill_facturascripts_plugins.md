# SKILL: Desarrollo de Plugins para FacturaScripts

**Descripción**: Esta es una base de conocimiento (skill) diseñada para instruir a una IA (como Claude) en la creación, mantenimiento y estructuración de plugins para el framework y ERP FacturaScripts (PHP).

---

## 1. Reglas Cardinales y Contexto General
1. **NUNCA MODIFICAR EL CORE**: FacturaScripts se actualiza constantemente. Todo desarrollo nuevo o modificación debe hacerse única y exclusivamente a través de un **Plugin**.
2. **Framework Propio**: FacturaScripts NO usa Laravel, Symfony ni CodeIgniter. Tiene su propio Kernel, enrutador, ORM (BaseModel) y motor de plantillas (Twig).
3. **Nomenclatura**: Los nombres de clases usan `StudlyCaps`, los métodos `camelCase` y las propiedades `camelCase`.
4. **Namespaces**: Todos los archivos dentro de un plugin deben tener el namespace del plugin: `namespace FacturaScripts\Plugins\NombreDelPlugin;`

## 2. Estructura de un Plugin
Todo plugin debe alojarse en la carpeta `Plugins/NombreDelPlugin/`. La estructura base recomendada es:

```text
Plugins/NombreDelPlugin/
├── facturascripts.ini      # Metadatos del plugin (versión, descripción)
├── Init.php                # Archivo de inicialización del plugin
├── Controller/             # Controladores (ListController, EditController, PanelController)
├── Model/                  # Modelos de datos (BaseModel)
├── Extension/              # Extensiones de Modelos o Controladores existentes
├── View/                   # Vistas Twig (.html.twig)
├── XMLView/                # Archivos XML para vistas automáticas (List, Edit)
├── Cron/                   # Tareas programadas
└── Test/                   # Pruebas unitarias PHPUnit
```

### 2.1 Archivo `facturascripts.ini`
Es obligatorio para que el sistema reconozca el plugin.
```ini
description = "Descripción de lo que hace el plugin."
version = 1.0
min_version = 2024.1
name = "NombreDelPlugin"
```

### 2.2 Archivo `Init.php`
Clase que hereda de `Base\Init`. Se ejecuta al cargar FacturaScripts. Aquí se inyectan extensiones, menús, o configuraciones iniciales.
```php
<?php
namespace FacturaScripts\Plugins\NombreDelPlugin;

use FacturaScripts\Core\Base\Init;

class Init extends Init
{
    public function init(): void
    {
        // Se ejecuta al cargar la página
    }

    public function update(): void
    {
        // Se ejecuta al activar o actualizar el plugin
    }
}
```

## 3. Controladores (Controllers)
Los controladores manejan la interfaz de usuario. FacturaScripts usa tres tipos principales para el CRUD automático.

### 3.1 ListController
Se usa para mostrar listados de un modelo.
* **Clase**: Hereda de `FacturaScripts\Core\Lib\ListViews\ListController` o similar.
* **Uso**: Automáticamente renderiza una tabla con filtros y paginación basados en un XML.
* **Filtros**: Para añadir filtros de búsqueda en PHP, usar `$this->addFilterSelect()`, `$this->addFilterAutocomplete()`, etc.

### 3.2 EditController
Se usa para crear/editar un registro individual.
* **Clase**: Hereda de `FacturaScripts\Core\Lib\ExtendedController\EditController`.

### 3.3 PanelController
Se usa para pantallas más complejas o configuraciones (como el Panel de Control).

*Nota: La interfaz visual se define casi en un 90% mediante archivos XML ubicados en `XMLView/`.*

## 4. Modelos de Datos (Models)
* **Clase**: Todo modelo debe heredar de `FacturaScripts\Core\Model\Base\BaseModel` (para tablas normales) o `ModelClass`.
* **Métodos principales**: `clear()`, `test()`, `save()`, `delete()`, `all()`.
* **Propiedades**: Las propiedades públicas del modelo deben coincidir exactamente con las columnas de la tabla en la base de datos.
* **Motor Base de Datos**: Es agnóstico (PostgreSQL, MySQL). FacturaScripts genera automáticamente las tablas basándose en el archivo `XMLView/Table/nombretabla.xml`.

```php
<?php
namespace FacturaScripts\Plugins\MiPlugin\Model;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Model\Base\ModelTrait;

class MiTabla extends ModelClass
{
    use ModelTrait;
    
    public $id;
    public $nombre;

    public function clear(): void
    {
        parent::clear();
        $this->id = null;
        $this->nombre = '';
    }

    public function tableName(): string
    {
        return 'mitabla';
    }
}
```

## 5. Vistas y XMLViews
La magia de FacturaScripts radica en los **XMLViews**.
En la carpeta `XMLView/`, puedes crear:
* `Table/mitabla.xml`: Define las columnas de la tabla de base de datos.
* `List/MiController.xml`: Define las columnas a mostrar en el ListController.
* `Edit/MiController.xml`: Define los campos del formulario en el EditController.

Si el plugin extiende un XML del core, el archivo XML del plugin se fusionará automáticamente sobrescribiendo o agregando nodos al XML original.

## 6. Extensiones (Modificar Core sin tocar Core)
Para modificar el comportamiento de un controlador o modelo existente:
1. Crea una clase en `Extension/Model/` o `Extension/Controller/`.
2. Extiende el comportamiento usando los *hooks* o métodos específicos.
3. Para reemplazar código HTML/Twig de una vista existente del Core, crea un archivo `.twig` en tu plugin replicando la ruta del original pero añadiendo tus bloques usando `{% extends '...' %}`.

## 7. La API REST
FacturaScripts integra una API REST automática bajo el endpoint `/api`.
* **Añadir un modelo a la API**: Solo necesitas crear una clase en `Controller/Api/` que herede de `FacturaScripts\Core\Lib\ExtendedController\ApiController` y enlazarla a tu modelo.
* **Autenticación**: Vía Basic Auth o Token.

## 8. Pruebas Unitarias (PHPUnit)
Cualquier código crítico o plugin para la venta debe incluir tests.
* Ubicación: `Test/`
* Extienden de `PHPUnit\Framework\TestCase`.
* Ejecución: Vía consola, cargando el autoload de composer de FacturaScripts.

---

## 🛑 INSTRUCCIONES ESTRICTAS PARA CLAUDE (PROMPT RULES) 🛑

Cuando el usuario te pida crear código para FacturaScripts, DEBES obedecer lo siguiente:

1. **ASUME EL CONTEXTO DEL PLUGIN**: Nunca sugieras editar un archivo fuera de `Plugins/TuPlugin/`.
2. **NUNCA USES LARAVEL/SYMFONY**: No uses `Illuminate\`, no uses Eloquent. Usa las clases `BaseModel`, `ListController`, `EditController` y las herramientas nativas de `FacturaScripts\Core\`.
3. **XML PRIMERO**: Cuando el usuario pida una pantalla para listar datos, genera el PHP del `ListController` y acompáñalo INMEDIATAMENTE de la estructura del archivo `XMLView/List/nombre.xml`. Sin el XML, el controlador fallará.
4. **TABLAS AUTOMÁTICAS**: Cuando el usuario pida una nueva tabla de base de datos, genera la clase PHP del `Model` e INMEDIATAMENTE genera el archivo `XMLView/Table/tabla.xml`. NO sugieras migraciones SQL brutas.
5. **NAMESPACE**: Asegúrate de que TODAS las clases generadas comiencen con `namespace FacturaScripts\Plugins\NombreDelPlugin\...` donde aplique.
6. **MÉTODOS OBLIGATORIOS EN MODELOS**: Asegúrate de sobreescribir y utilizar `clear()` y `tableName()`.
7. **INCLUSIÓN DE INIT**: Siempre que crees un plugin desde cero, asegúrate de proporcionar un archivo `Init.php` básico y el `facturascripts.ini`.
8. **CLARIDAD EN ERRORES**: Si el usuario reporta un error de vista, recuérdale revisar la sintaxis de sus archivos XML en `XMLView`. Es la causa más común de errores de renderizado.
