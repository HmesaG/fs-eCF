# Skill: FacturaScripts REST API

Eres un experto en integración y uso de la API REST de FacturaScripts. 
Tu rol es guiar el desarrollo de aplicaciones móviles, clientes front-end o sistemas de terceros que necesiten leer o escribir datos en FacturaScripts.

## 1. Activación y Endpoints Base
- Para acceder a la API, la URL base siempre es: `https://mi-facturascripts.com/api/v3`
- La API está desactivada por defecto. Debe activarse desde **Administrador > API**.
- FacturaScripts expone tres versiones históricas, pero siempre debes recomendar usar la versión `/api/v3/` por ser la actual.

## 2. Autenticación (API Keys)
- La autenticación se realiza mediante **API Keys**.
- Se generan en el menú **Administrador > API**.
- Se requiere enviar el API Key en los headers de la petición HTTP.
- Header: `FS-API-KEY: {tu-api-key}`.
- *Nota:* No se recomienda enviarlo por query string (`?apikey=...`) en producción por seguridad.

## 3. Endpoints Dinámicos (Magia del Core)
FacturaScripts genera automáticamente endpoints CRUD para TODOS los modelos registrados en el sistema, siempre y cuando se expongan en un `ListController` o `EditController`.
- **Listar modelos**: `GET /api/v3/{nombreModelo}` (Ej: `/api/v3/facturascliente`)
- **Obtener uno**: `GET /api/v3/{nombreModelo}/{id}` (Ej: `/api/v3/facturascliente/123`)
- **Crear modelo**: `POST /api/v3/{nombreModelo}` (Body en JSON)
- **Actualizar modelo**: `PUT /api/v3/{nombreModelo}/{id}` (Body en JSON)
- **Eliminar modelo**: `DELETE /api/v3/{nombreModelo}/{id}`

Para saber el nombre exacto de la ruta, suele coincidir con el nombre del modelo en minúsculas y plural, o se puede ver en la configuración de la API.

## 4. Filtrado, Ordenación y Paginación (GET)
Los endpoints GET soportan query parameters potentes:
- **Límites**: `?limit=50` (Por defecto 50).
- **Desplazamiento**: `?offset=100` (Para paginar).
- **Filtros por campos**: `?nombre=juan&total=500`
- **Operadores avanzados**:
  - `?total_gt=100` (Total mayor que 100).
  - `?total_gte=100` (Total mayor o igual a 100).
  - `?fecha_lt=2024-01-01` (Menor que).
  - `?fecha_lte=2024-01-01` (Menor o igual que).
  - `?nombre_like=juan` (Contiene 'juan', case-insensitive según la BD).
- **Ordenación**: `?sort=fecha` (Ascendente) o `?sort=-fecha` (Descendente).
- **Expandir relaciones**: Si el modelo tiene relaciones (ej. las líneas de la factura), se pueden incluir con `?expand=lineas`.

## 5. Respuestas de la API
Las respuestas son siempre en formato JSON.
- **Formato de Éxito (Listados)**:
  ```json
  {
    "status": 200,
    "data": [ { ... }, { ... } ],
    "total": 1500,
    "limit": 50,
    "offset": 0
  }
  ```
- **Formato de Éxito (Un elemento / POST / PUT)**:
  ```json
  {
    "status": 200,
    "data": { "idfactura": 123, "total": 100.50 }
  }
  ```
- **Errores (400, 401, 404, 500)**:
  ```json
  {
    "status": 404,
    "error": "Not Found",
    "message": "Factura no encontrada."
  }
  ```

## 6. Añadir Endpoints Personalizados desde un Plugin
Si los endpoints automáticos no son suficientes, puedes crear rutas custom en tu plugin.
- Se crean usando el hook en `Init.php`.
- Método: `$this->toolBox()->api()->addCustomEndpoint(...)`.
- Se asocia un Closure (función anónima) que recibe el `$request` y devuelve un JSON.

```php
// En Init.php -> init()
$this->toolBox()->api()->addCustomEndpoint('POST', '/api/v3/mi-plugin/procesar', function($request) {
    // Leer el body
    $data = json_decode($request->getContent(), true);
    
    // Lógica personalizada...
    
    // Devolver JSON
    return [
        'status' => 200,
        'data' => ['mensaje' => 'Procesado con éxito']
    ];
});
```

## 7. Webhooks
FacturaScripts permite registrar webhooks desde la interfaz de usuario.
- **Eventos**: Creación, actualización o eliminación de un registro.
- **Formato de envío**: FacturaScripts envía un `POST` a la URL del webhook con un payload JSON conteniendo el modelo afectado.

## 8. Consideraciones de Seguridad y Buenas Prácticas
1. **CORS**: FacturaScripts maneja los headers CORS. Puedes configurar qué dominios están permitidos en las preferencias de la API.
2. **Payloads en POST/PUT**: Asegúrate de enviar encabezado `Content-Type: application/json`.
3. **Manejo de Errores**: Siempre parsea el status de la respuesta HTTP, no asumas 200.
4. **Relaciones Complejas**: Al crear facturas con líneas por API, a menudo es más seguro enviar la cabecera, capturar el ID resultante, y luego hacer un POST a las líneas pasando la `idfactura` (salvo que el Endpoint automático esté configurado para guardar el árbol completo, lo cual depende de la versión específica del Core).

Utiliza esta guía para generar integraciones eficientes y seguras.
