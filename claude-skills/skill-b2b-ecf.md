# Skill: Implementación B2B (e-CF) en eCF_GMV

Este skill documenta la lógica y flujo de comunicación entre contribuyentes (Business-to-Business) implementada en el plugin eCF_GMV para FacturaScripts.

## 1. Flujo de Recepción (Incoming)
El emisor de una factura (vendedor) debe enviar el XML firmado al receptor (comprador).

### Endpoint de Recepción
- **Controlador**: `Controller/ApiRecepcion.php`
- **URL**: `index.php?page=ApiRecepcion` (Accesible públicamente como requiere DGII).
- **Lógica**:
  1. Recibe el XML vía `POST` (raw body).
  2. Valida que sea un XML bien formado.
  3. Registra el evento en `ecf_log` con tipo `RECEPCION_B2B`.
  4. Extrae `RNCEmisor` y `eNCF`.
  5. **Genera ARECF** (Acuse de Recibo Electrónico): XML que confirma la recepción física del archivo.
  6. **Firma el ARECF** con el certificado `.p12` del receptor.
  7. Retorna el XML del ARECF firmado en la respuesta HTTP.

## 2. Aprobación Comercial (Workflow)
Una vez recibido el e-CF y generado el acuse (ARECF), el receptor debe procesar la factura comercialmente.

### Interfaz de Gestión
- **Controlador**: `Controller/AprobacionComercial.php`
- **Vista**: `XMLView/AprobacionComercial.xml`
- Muestra todos los logs de tipo `RECEPCION_B2B`.
- El usuario puede revisar el XML y pulsar "Aprobar".

### Lógica de Aprobación (ACECF)
1. Al pulsar "Aprobar", se dispara `aprobarAction()`.
2. Se genera el XML **ACECF** (Aprobación Comercial Electrónica).
3. Se firma el ACECF.
4. **Localización del Emisor**: 
   - Se consulta el **Directorio DGII** (`consultarDirectorio`) usando el RNC del emisor para obtener su URL de recepción.
   - Si no está en el directorio, se busca en la ficha del Cliente en FacturaScripts (campo `url_recepcion_ecf`).
5. **Envío**: Se envía el ACECF firmado a la URL destino vía `POST`.

## 3. Clases Involucradas
- `DgiiXmlGenerador::generarARECF($datos)`: Crea el XML base del acuse.
- `DgiiXmlGenerador::generarACECF($datos)`: Crea el XML base de la aprobación.
- `DgiiApiService::enviarACECF($url, $xmlFirmado, ...)`: Realiza el envío HTTP al otro contribuyente.
- `DgiiApiService::consultarDirectorio($rnc)`: Obtiene la URL de recepción oficial desde DGII.

## 4. Consideraciones Técnicas
- **Timeouts**: Las conexiones B2B pueden ser lentas. Se usa el timeout configurado en `ECFConfiguracion`.
- **Errores**: Si el envío del ACECF falla, el sistema muestra un aviso, pero el registro queda marcado para reintento manual o auditoría.
- **Seguridad**: El endpoint de recepción es público. Aunque no requiere token de FacturaScripts, la seguridad reside en la validación de la firma XML (XMLDSig) que asegura que el emisor es quien dice ser.

## 5. Próximos Pasos / Mejoras
- Implementar la validación de firma en `ApiRecepcion` para mayor seguridad.
- Automatizar la creación de facturas de proveedor (compras) a partir del XML recibido en `ApiRecepcion`.
