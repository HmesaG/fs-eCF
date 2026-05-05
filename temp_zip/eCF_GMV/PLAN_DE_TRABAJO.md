# PLAN DE TRABAJO - PLUGIN eCF_GMV (Optimización 100% Producción)

Este documento consolida el plan de implementación y la lista de tareas para completar el plugin eCF_GMV de FacturaScripts, asegurando el cumplimiento con la DGII y la trazabilidad total.

---

## 1. PLAN DE IMPLEMENTACIÓN

### Resumen
Optimizar el plugin para producción, incluyendo validación XSD, procesos B2B, reporte de consumo (RFCE) y auditoría completa de XMLs.

### Cambios Propuestos

#### A. Validación Estricta y Generación XML (XSD Compliance)
- **DgiiXmlGenerador.php**: Implementar `validarConXSD($xml, $tipo)` usando `DOMDocument::schemaValidate()`. Limpieza recursiva de nodos vacíos.
- **DgiiXmlFirmador.php**: Mantener canonicalización inclusiva `C14N(false, false)`.

#### B. Persistencia y Trazabilidad (Auditoría XML)
- **ECFLog.php**: Añadir columnas `xml_sin_firma` y `xml_firmado`.
- **EditECFLog.xml**: Crear un visor de 3 paneles (Original, Firmado, Respuesta DGII).
- **DgiiApiService.php**: Integrar el guardado de los dos estados del XML y la respuesta íntegra de la DGII.

#### C. Procesos B2B y Recepción (Aprobación Comercial)
- **ApiRecepcionController.php**: Endpoint para recibir XMLs. Generación automática de `ARECF`.
- **AprobacionComercial.xml**: Interfaz para gestionar facturas recibidas y generar `ACECF`.

#### D. Reporte de Consumo (RFCE)
- **DgiiResumenServicio.php**: Agrupación de facturas E32 (Consumo) y generación de XML `RFCE 32`.

#### E. Representación Impresa (PDF)
- **ECFPdfGenerator.php**: Generar QR con `CodigoSeguridad` (6 caracteres del `SignatureValue`).

#### F. Configuración y UI
- **EditECFConfiguracion.xml**: Botón "Probar Conexión" para validar el certificado .p12 inmediatamente.

---

## 2. LISTA DE TAREAS (TODO)

- [x] Auditoría de XML y Firma
    - [x] Refactorizar `DgiiXmlGenerador.php` (limpieza de nodos)
    - [x] Refactorizar `DgiiXmlFirmador.php` (canonicalización C14N)
- [ ] Validación Estricta (XSD)
    - [ ] Implementar `validarConXSD()` en `DgiiXmlGenerador`
    - [ ] Test de validación contra todos los XSDs
- [ ] Persistencia y Visor (Auditoría)
    - [ ] Expandir tabla `ecf_log` (xml_sin_firma, xml_firmado)
    - [ ] Actualizar `EditECFLog.xml` (Visor de 3 columnas)
    - [ ] Integrar guardado en `DgiiApiService`
- [ ] Comunicación y Gestión de Estados
    - [x] Refactorizar `DgiiApiService.php` (Flujo Semilla)
    - [ ] Implementar Polling en `Cron.php` (Refinar estados)
- [ ] Procesos B2B (Aprobación Comercial)
    - [ ] Implementar `ApiRecepcionController.php`
    - [ ] Generación automática de `ARECF`
    - [ ] Vista `AprobacionComercial.xml` para `ACECF`
    - [x] Implementar Polling en `Cron.php` (Refinar estados)
- [x] Procesos B2B (Aprobación Comercial)
    - [x] Implementar `ApiRecepcionController.php`
    - [x] Generación automática de `ARECF`
    - [x] Vista `AprobacionComercial.xml` para `ACECF`
- [x] Reporte de Consumo (RFCE)
    - [x] Implementar `DgiiResumenServicio.php`
    - [x] Tarea programada para envío diario de RFCE
- [x] Representación Impresa (PDF)
    - [x] Crear `ECFPdfGenerator.php` con QR y Código de Seguridad (6 chars)
- [x] UI y Configuración
    - [x] Botón de "Probar Conexión" en `EditECFConfiguracion.xml`
- [x] Pruebas Finales y Empaquetado
    - [x] Generar ZIP final v1.0.0

---

## 3. PLAN DE VERIFICACIÓN

### Pruebas Automatizadas
- `Test/XsdValidationTest.php`: Validar XMLs contra esquemas.
- `Test/LogPersistenceTest.php`: Verificar guardado de XMLs y respuestas.

### Pruebas Manuales
1. Validar conexión con .p12 real.
2. Emitir factura y verificar persistencia en Logs.
3. Escanear QR y validar código de seguridad.
4. Simular recepción B2B.
