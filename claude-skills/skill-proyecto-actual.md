# Skill: Estado Actual Proyecto eCF_GMV (FacturaScripts)

## 1. Resumen del Proyecto
- **Nombre**: eCF_GMV
- **Versión Actual**: 1.11
- **Objetivo**: Facturación Electrónica DGII (República Dominicana).
- **Estado**: Fase de optimización para producción y B2B.

## 2. Observaciones Críticas (Engram)
- **Estado Inicial**: v1.1 (Registrado el 2026-05-04).
- **Control de Versiones**: Se está trabajando sobre la rama de producción optimizada.
- **Trazabilidad**: Implementada auditoría de XMLs (sin firma, firmado, respuesta) en la tabla `ecf_log`.
- **Entorno (IMPORTANTE)**: El desarrollo es local en **Windows**, pero la instalación/despliegue real está en **Linux mediante Dokploy**. 
- **URL Base Pruebas**: `http://fs-8fink9-9e0299-31-97-100-82.traefik.me` (Dashboard: `/Dashboard`). Esto debe tenerse en cuenta al hacer pruebas HTTP o validaciones de rutas absolutas.

## 3. Arquitectura y Componentes Clave
- **Generación XML**: `DgiiXmlGenerador.php`. Usa `DOMDocument` con limpieza recursiva de nodos vacíos (REQUISITO DGII).
- **Firma Digital**: `DgiiXmlFirmador.php`. Canonicalización `C14N(false, false)`.
- **API DGII**: `DgiiApiService.php`. Maneja el flujo de Semilla -> Token -> Recepción -> Polling.
- **Automatización**: `Cron.php`.
    - Consulta facturas en estado "3" (En proceso) cada 15 min.
    - Envío diario de RFCE (Resumen de Facturas de Consumo < 250k).
- **B2B**: `ApiRecepcionController.php` para recibir XMLs de terceros y generar `ARECF`/`ACECF`.

## 3.1 Estructura del Plugin (Cumplimiento FacturaScripts)
- **Model**: Heredan de `Base\Model`. Ej. `ECFConfiguracion.php`, validan y encapsulan datos.
- **Controller**: Extienden de `ListController` / `EditController` usando `loadData()` de forma segura.
- **Table**: Definiciones estructurales puras en `.xml` (`ecf_log.xml`) y extensiones a tablas nativas (`clientes.xml`, `facturascli.xml`).
- **XMLView**: Vistas dinámicas sin código duro (`ListECFConfiguracion.xml`, `menu.xml`).
- **Extension**: Hooks limpios sobre controladores, modelos y tablas del core sin alterar archivos fuente originales.

## 4. Recordatorios de Desarrollo (FacturaScripts Pitfalls)
- **getModelClassName()**: DEBE retornar el nombre corto de la clase (ej. `'ECFLog'`), NUNCA el FQCN.
- **Redirecciones**: Usar `$this->redirect('Controlador?param=1')`. No existe `Tools::url()`.
- **Filtros en Listado**: Usar `loadData()` para inyectar `DataBaseWhere`. `setFixedWhere()` NO existe en `ListController`.
- **ZIP de Instalación**: En Windows, usar el script `create_zip.ps1` que fuerza separadores `/`. El kernel rechaza ZIPs con `\`.

## 5. Pendientes (Según PLAN_DE_TRABAJO.md)
- [x] Refinar polling de estados en `Cron.php` (Completado).
- [x] Implementar recepción B2B y automatización de compras (Completado).
- [ ] Pruebas exhaustivas de recepción B2B con otros contribuyentes reales.
- [ ] Validación final de QR y Representación Impresa (PDF) con la APP de DGII.

---
*Este documento es un 'Engram' del estado del proyecto para facilitar la continuidad del desarrollo.*
