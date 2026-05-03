# Guía Completa de Facturación Electrónica DGII (e-CF) - República Dominicana

Esta guía técnica definitiva cubre el ciclo de vida completo de la facturación electrónica en República Dominicana (e-CF), basada en la documentación oficial de la Dirección General de Impuestos Internos (DGII). Está diseñada como referencia técnica exhaustiva para implementar un sistema ERP en producción.

---

## 1. Marco Legal
* **Ley 32-23**: Ley General de Facturación Electrónica. Establece el uso obligatorio de la factura electrónica, regula el sistema fiscal electrónico y dispone las facilidades fiscales.
* **Decreto 587-24**: Reglamento de Aplicación de la Ley 32-23. Detalla los procedimientos operativos, técnicos y sanciones.
* **Norma General 01-2020**: Regula el proceso de emisión y uso de los Comprobantes Fiscales Electrónicos (e-CF).
* **Obligatoriedad y Plazos**: Implementación por fases dependiendo de la clasificación del contribuyente (Grandes Nacionales, Locales, MIPYMES). Existen calendarios oficiales que dictan la fecha límite de transición obligatoria para cada grupo.

## 2. Requisitos Previos
* **Inscripción RNC**: Poseer un Registro Nacional de Contribuyentes (RNC) activo y actualizado.
* **Acceso a OFV**: Tener clave de acceso vigente a la Oficina Virtual de la DGII.
* **Alta NCF**: Estar autorizado para emitir Números de Comprobantes Fiscales.
* **Certificado Digital (.p12)**:
  * Debe ser emitido por una entidad certificadora autorizada en RD (ej. Avansi, Viafirma, Optic).
  * Formato estándar P12/PFX exportado con su llave privada.
  * Vigencia de 1 a 2 años.
* **Delegación de Roles**: Configurar mediante la OFV los roles técnicos del certificado asociado al RNC:
  * *Administrador*: Configura y delega roles.
  * *Firmante*: Autorizado a firmar documentos XML e-CF.
  * *Aprobador Comercial*: Autorizado a enviar la Aprobación Comercial (ACECF) para facturas recibidas de crédito.
* **Formulario FI-GDF-016**: Completar la solicitud oficial para ingresar al modelo de facturación electrónica.

## 3. Tipos de e-CF
| Tipo | Nombre Completo | Uso Principal | Reglas y Diferencias |
|------|-----------------|---------------|----------------------|
| **31** | Factura de Crédito Fiscal | Para contribuyentes con derecho a deducción de ITBIS y/o ISR. | Obligatorio el desglose de impuestos e información del comprador (RNC). |
| **32** | Factura de Consumo | Consumidor final, no da derecho a deducción. | Comprador opcional si el monto es < RD$250,000. Si es < 250k, se envía a través de RFCE. |
| **33** | Nota de Débito | Modifica e-CF anteriores (aumento de valor). | Requiere indicar el e-NCF modificado. |
| **34** | Nota de Crédito | Modifica e-CF anteriores (descuentos, devoluciones, anulaciones parciales). | Requiere indicar el e-NCF original e `IndicadorNotaCredito`. |
| **41** | Compras | Proveedores informales. | El emisor es quien compra y el "comprador" es el proveedor (se invierte el flujo lógico). |
| **43** | Gastos Menores | Gastos misceláneos, de caja chica. | No suele tener desglose detallado hacia proveedores RNC específicos. |
| **44** | Regímenes Especiales | Ventas a entidades en regímenes fiscales especiales. | Exentos de ITBIS por ley. |
| **45** | Gubernamental | Ventas a entidades del Estado (ministerios, ayuntamientos). | RNC comprador debe estar marcado como gubernamental en DGII. |
| **46** | Exportación | Venta de bienes/servicios al exterior. | No incluye ITBIS. |
| **47** | Pagos al Exterior | Pagos por servicios/rentas a extranjeros no domiciliados. | Se aplica retención de ISR. |

## 4. Estructura Técnica del XML
La estructura oficial requiere apegarse estrictamente a los XSD de la DGII.

* **Estructura General (`ECF`)**:
  * `Encabezado`:
    * `IdDoc`: e-NCF, Fecha Vencimiento, Tipo, Montos.
    * `Emisor`: RNC, Razón Social, Dirección, Fecha Emisión.
    * `Comprador`: RNC/Cédula, Razón Social.
    * `Totales`: TotalMontoBruto, TotalDescuento, TotalITBIS, TotalMontoNeto.
  * `DetallesItems`: Elementos facturados (Cantidad, Precio, Nombre, ITBIS por ítem).
* **Reglas de XML**:
  * Codificación UTF-8 obligatoria.
  * No permitir **tags vacíos** (ej. `<TablaFormasPago></TablaFormasPago>`). Si no hay formas de pago, se omite el nodo padre si no es obligatorio.
  * Valores como `#e` en el Excel técnico = Omitir nodo XML por completo, no incluirlo vacío.
* **Formato e-NCF**: La letra **E** + tipo e-CF (2 dígitos) + secuencia (10 dígitos). Ej. `E310000000001` (Total 13 caracteres).
* **Firma Digital XMLDSig**:
  * Transform: `http://www.w3.org/2000/09/xmldsig#enveloped-signature`
  * Canonicalization: `http://www.w3.org/TR/2001/REC-xml-c14n-20010315`
  * Digest: SHA-256 (`http://www.w3.org/2001/04/xmlenc#sha256`)
  * SignatureMethod: RSA-SHA256
  * Referencia URI vacía: `URI=""` (Significa firmar todo el documento contenedor).

## 5. Ambientes y URLs
DGII dispone de tres ambientes. Deben parametrizarse en la aplicación:

| Ambiente | Host Base | Uso |
|----------|-----------|-----|
| **TesteCF** | `https://ecf.dgii.gov.do/Testecf` | Pruebas y Certificación de Software. |
| **CerteCF** | `https://ecf.dgii.gov.do/Certecf` | Homologación final del contribuyente (poco usado, se salta a Producción). |
| **eCF (PROD)** | `https://ecf.dgii.gov.do/eCF` | Producción Oficial. |

**Endpoints comunes (Relativos al Host Base, ej. `/wsrest/api/...`)**:
* **Semilla**: `/wsrest/api/Autenticacion/api/Autenticacion/Semilla` (GET)
* **Validar Semilla**: `/wsrest/api/Autenticacion/api/Autenticacion/ValidarSemilla` (POST)
* **Recepción e-CF**: `/wsrest/api/ecf` (POST)
* **Consulta Estado**: `/wsrest/api/Consultas/Estado/{RNC}/{eNCF}/{TrackId}` (GET)
* **Recepción RFCE (Consumo < 250k)**: Ojo, dominio y endpoint distinto: `https://fc.dgii.gov.do/ecf/api/RecepcionFC` (TesteCF y PROD).
* **Aprobación Comercial**: `/wsrest/api/AprobacionComercial` (POST)
* **Anulación**: `/wsrest/api/Anulacion` (POST)
* **Directorio (Compradores)**: `/wsrest/api/Consultas/Directorio/{RNC}` (GET)
* **Estatus Servicio**: `/wsrest/api/Consultas/EstadoServicios` (GET)

## 6. Flujo de Autenticación
1. **GET Semilla**: Llamar al endpoint `Semilla`. DGII devuelve un XML con un string alfanumérico `<Semilla>`.
2. **Firmar Semilla**: Se toma el XML devuelto, y se firma con el Certificado Digital (.p12) usando XMLDSig (RSA-SHA256).
3. **POST Validar Semilla**: Enviar el XML de la semilla ya firmado de vuelta a la DGII.
4. **Respuesta Token**: DGII valida la firma contra el RNC y devuelve un Token JWT (`token`).
5. **Persistencia**: El token tiene validez de **1 hora**. El ERP debe cachear el token para no pedir uno nuevo por cada factura, mejorando tiempos de respuesta y evitando el *rate-limiting*. En todas las demás llamadas se usa en el header: `Authorization: Bearer <token>`.

## 7. Flujo Completo de Emisión
1. **Generar XML**: ERP toma datos, arma XML estructurado válido contra el XSD según tipo (E31, E32, etc).
2. **Validación XSD Local** (Recomendado): Validar localmente antes de intentar firmar/enviar para ahorrar tiempo.
3. **Firmar Digitalmente**: Aplicar XMLDSig Enveloped al XML.
4. **Obtener Token**: Recuperar de caché o ejecutar flujo de Autenticación.
5. **Enviar a DGII**: POST a la URL de Recepción e-CF.
6. **Recibir TrackId**: DGII devuelve un ID de seguimiento (ej. `d1a5b8...`). Esto no significa que está aprobado, solo recibido.
7. **Consultar Estado (Polling)**: Tras unos segundos (1-3 segs), se hace un GET Consulta Estado enviando RNC, eNCF, TrackId.
8. **Estados Posibles**:
   * `0`: No encontrado
   * `1`: Aceptado (Válido para fines fiscales)
   * `2`: Rechazado (Hay errores en formato, suma, RNC, eNCF repetido. Ver MensajeError)
   * `3`: En Proceso (Esperar y re-consultar)
   * `4`: Aceptado Condicional (Válido, pero con advertencias)
9. **Enviar a Receptor**: Si es B2B (E31, E33, E34...), enviar el XML original por correo/servicio web según la información que dicte el **Directorio e-CF** de DGII para ese RNC.
10. **Receptor envía ARECF** (Acuse de Recibo): Respuesta estándar indicando que su sistema procesó la recepción.
11. **Receptor envía ACECF** (Aprobación Comercial): Respuesta manual/automática del cliente indicando conformidad con la factura (Solo E31 y E34 de crédito comercial).

## 8. Flujo RFCE (Resumen Consumo < RD$250,000)
* **Cuándo aplica**: Para todas las facturas de Consumo (E32) cuyo monto total sea **menor a 250,000 DOP**.
* **Diferencia técnica**: No se envían uno a uno de inmediato. Se envían sin detalles exhaustivos o se consolida en un XML simplificado a un endpoint de alto rendimiento.
* **Endpoint diferente**: `https://fc.dgii.gov.do/ecf/api/RecepcionFC`
* **Respuesta**: Responde inmediatamente (no hay TrackId que consultar luego). Devuelve `codigo`, `estado`, `encf`, `secuenciaUtilizada`. Es síncrono.

## 9. Proceso de Certificación DGII (14 Pasos)
Antes de pasar a producción, el software o empresa debe certificarse.

* **Etapa 1: Solicitud**: Enviar formulario FI-GDF-016.
* **Etapa 2: Set de Pruebas (TestECF)**
  * **Paso 1: Registro del Software**: En la OFV se registra el nombre, IP, y URLs del software: URL Recepción, URL Aprobación, URL Autenticación (para comunicación B2B).
  * **Paso 2 al 12: Pruebas con Excel**: Se descarga un Excel con casos de prueba asignados por el asignador de DGII. Se deben emitir XML **exactos** a los descritos en el Excel.
    * **Orden Obligatorio**: 
      1. Todos los eCF base: 31, 32(≥250k), 41, 43, 44, 45, 46, 47.
      2. Documentos dependientes: 33 y 34 (ya que requieren referenciar e-NCFs de los generados en el paso previo).
      3. Flujo RFCE-32 (Aprobación de la plataforma de consumo).
      4. E32 con valor menor a 250k a través de RFCE.
  * **Paso 5: Representación Impresa (RI)**: Cargar un PDF generado por su ERP, de tamaño menor a 10MB, mostrando que cumple todos los requisitos visuales.
  * **Paso 13: Declaración Jurada**: Enviar un XML firmado declarando que el software fue probado con éxito.
* **Etapa 3: Habilitación**: DGII revisa todo, emite resolución y otorga acceso a Producción.

## 10. Representación Impresa (RI)
El PDF de la factura que se entrega al cliente físico o por correo debe contener de forma legible:
1. Leyenda "Factura Electrónica" o el tipo que sea.
2. Número **e-NCF** claramente visible.
3. **Fecha Vencimiento Secuencia** (Fecha hasta la cual es válida la serie).
4. **Código de Seguridad**: Últimos 6 dígitos del código extraído de la firma (o el digest provisto).
5. **Fecha y Hora de Firma**.
6. **Código QR**: Debe apuntar a la URL de consulta pública de DGII. La estructura de la URL del QR es un estándar (RNC, eNCF, Monto, Fecha, CodigoSeguridad).
7. RNC/Nombre del emisor y RNC/Nombre del comprador (si aplica).
8. Desglose claro del ITBIS y otros impuestos por ítem y/o en el pie.

## 11. Comunicación Emisor-Receptor (B2B)
El sistema debe estar preparado para **recibir** y **aprobar** e-CF de otros proveedores si son emisores electrónicos.
* Consultar el **Directorio** de DGII para conocer la URL de Recepción de mi cliente/proveedor.
* **Acuse de Recibo (ARECF)**: El ERP que recibe el e-CF lo valida sintácticamente (XSD, firma). Retorna estado 0 (Recibido correctamente) o 1 (No Recibido). Motivos de rechazo: 1=Error especificación, 2=Error firma, 3=Duplicado, 4=RNC no es mío.
* **Aprobación Comercial (ACECF)**: Luego de recibirlo, un usuario humano o regla de ERP valida las cantidades/precios, y envía un XML (ACECF) indicando 1=Aprobado o 2=Rechazado al proveedor y a la DGII.

## 12. Anulación (ANECF)
* **Cuándo anular**: Para invalidar secuencias (e-NCFs) que se dañaron, saltaron o no se usaron (no para anular una factura emitida que ya se envió al cliente; para eso se usa Nota de Crédito E34).
* **Estructura XML**: Contiene rango `Desde` y `Hasta` de la secuencia a anular.
* **Límites**: Hasta 10 tipos de comprobante por XML de anulación, y máximo 10,000 secuencias contiguas por tipo.

## 13. Errores Comunes y Soluciones
* **Error de Firma (Reference URI)**: Usualmente pasa si firmas elementos internos en vez del XML completo. Asegúrate que `Reference URI=""`.
* **Token Expirado (401 Unauthorized)**: Implementar auto-refresh del token si la respuesta es 401, volver a firmar semilla e intentar.
* **Errores de Sumatoria (Centavos)**: DGII es estricto. La suma de `MontoItem * Cantidad` debe cuadrar exactamente con los totales globales, cuidando el redondeo a 2 decimales bancario.
* **Certificado Revocado/No Delegado**: Error al validar semilla. Ir a la OFV y asegurarse que la cédula del dueño de la firma esté en "Delegación e-CF" bajo Firmante.
* **XML Invalid (XSD Error)**: Nodos vacíos `<Nombre></Nombre>`, uso de caracteres `&` (usar `&amp;`), omitir el BOM UTF-8.

## 14. Checklist Pre-Producción
- [ ] Roles de "Firmante" y "Aprobador" delegados en OFV de Producción.
- [ ] URLs apuntando a `https://ecf.dgii.gov.do/eCF`.
- [ ] Cacheado de token de autenticación funcionando correctamente.
- [ ] Carga de secuencias oficiales y fecha de vencimiento configurada en el ERP.
- [ ] Endpoints propios (Recepción, Aprobación) publicados en internet con HTTPS válido para recibir B2B.
- [ ] Generación correcta del Código QR y Código de Seguridad de 6 dígitos en los PDFs (Representación Impresa).
- [ ] Mecanismo de reintento implementado para facturas en estado `3 (En Proceso)`.

## 15. Lo que normalmente se olvida o causa problemas
1. **La cola de trackIds en proceso**: Muchos programadores no prevén que la DGII a veces se toma minutos en procesar en horas pico. Si no implementas una tarea programada (cron) para re-consultar los TrackIDs en estado `3`, las facturas quedan "en limbo".
2. **Recepción RFCE**: Se olvida parametrizar que las facturas de consumo (E32) < 250k DOP van a un endpoint HTTP totalmente diferente (`fc.dgii.gov.do`).
3. **Notas de Crédito a Facturas Viejas**: A veces hay que hacer NC a una factura B01 (viejo esquema impreso), y el ERP obliga a referenciar un e-NCF. La lógica debe permitir referenciar el NCF tradicional 11/19 caracteres en las Notas de Crédito.
4. **Gestión de redondeos**: Si una factura tiene descuento prorrateado por línea y causa 3 o 4 decimales, el envío a DGII fracasará si la suma global no cuadra exactamente a 2 decimales. 
5. **No actualizar los catálogos**: La DGII actualiza ocasionalmente los catálogos de monedas, unidades de medida y retenciones. El ERP debe permitir inyectarlos de forma dinámica sin recompilar.
