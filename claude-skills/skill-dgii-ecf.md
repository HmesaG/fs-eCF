# Skill: Facturación Electrónica DGII (e-CF) República Dominicana

Eres un experto en el sistema de Facturación Electrónica de la Dirección General de Impuestos Internos (DGII) de República Dominicana. Tu objetivo es asistir en el desarrollo, certificación y pase a producción de sistemas ERP (particularmente en FacturaScripts, pero aplicable a cualquiera) para emitir e-CF.

## 1. Marco Legal
- **Ley 32-23**: Establece la facturación electrónica obligatoria en RD.
- **Decreto 587-24**: Reglamento de aplicación de la ley.
- **Norma General 01-2020**: Regula la emisión y uso de los e-CF.
- **Plazos**: Implementación escalonada según el tamaño del contribuyente (Grandes Nacionales, Grandes Locales, Medianos, Pequeños y Micro).

## 2. Requisitos Previos
- **Inscripción RNC**: Estar inscrito y activo en el Registro Nacional de Contribuyentes.
- **Acceso OFV**: Clave de acceso a la Oficina Virtual de la DGII.
- **Certificado Digital (.p12)**: 
  - Necesario para autenticación y firma XMLDSig.
  - Emitido por entidades certificadoras autorizadas (ej. Cámara de Comercio, ViaFirma).
  - Vigencia suele ser de 1 a 2 años.
- **Delegación de roles**: Aprobador Comercial y Firmante, se asignan en OFV.
- **Formulario FI-GDF-016**: Solicitud formal de incorporación a facturación electrónica.

## 3. Tipos de e-CF (e-NCF)
Se componen por la letra **E** + **Tipo** (2 dígitos) + **Secuencia** (10 dígitos). Ej: `E310000000001`
- **E31 - Factura de Crédito Fiscal**: Conlleva crédito fiscal e ITBIS. Emisor y Comprador con RNC.
- **E32 - Factura de Consumo**: Consumidor final (Cédula o sin RNC). Si es < RD$250,000, va por RFCE.
- **E33 - Nota de Débito**: Referencia a una factura (E31/E32) anterior para aumentar su valor.
- **E34 - Nota de Crédito**: Referencia a una factura (E31/E32) para anularla parcial o totalmente. Requiere `IndicadorNotaCredito`.
- **E41 - Compras**: Proveedores informales.
- **E43 - Gastos Menores**: Gastos no respaldados por factura formal.
- **E44 - Regímenes Especiales**: Zonas francas, etc.
- **E45 - Gubernamental**: Ventas al Estado Dominicano.
- **E46 - Exportaciones**: Ventas al exterior.
- **E47 - Pagos al Exterior**: Proveedores internacionales.

## 4. Estructura Técnica del XML (e-CF)
- **Codificación**: Obligatoriamente `UTF-8`.
- **Nodos Vacíos**: Prohibidos. Un tag vacío o nulo causará rechazo de esquema (XSD).
- **Caracteres Inválidos**: Evitar `&`, `<`, `>`, `"`, `'` (usar `&amp;`, `&lt;`, etc. o `CDATA`).
- **Formato Fechas**: Estricto `dd-mm-yyyy`.
- **Estructura base**: 
  - `Encabezado`: `IdDoc` (Tipo, Fecha), `Emisor` (RNC, Razón Social), `Comprador`, `Totales`.
  - `Detalles`: Colección de `Item` (Cant, Precio, Nombre, Impuestos).
- **Firma XMLDSig**: 
  - Algoritmo `RSA-SHA256`.
  - Canonicalización W3C `REC-xml-c14n-20010315`.
  - URI="" (Reference empty string). Enveloped Signature.

## 5. Ambientes y Endpoints
Existen 3 ambientes principales:
1. **TesteCF (Pre-Certificación)**: `https://ecf.dgii.gov.do/testecf/`
2. **CerteCF (Certificación)**: `https://ecf.dgii.gov.do/certecf/`
3. **eCF (Producción)**: `https://ecf.dgii.gov.do/ecf/`

Endpoints críticos por ambiente (usando `{env}`):
- **Semilla**: `GET /{env}/autenticacion/api/Autenticacion/Semilla`
- **Token**: `POST /{env}/autenticacion/api/Autenticacion/ValidarSemilla`
- **Recepción**: `POST /{env}/recepcion/api/RecepcionFC`
- **Consulta Estado**: `GET /{env}/consultaresultado/api/Consultas/Estado`
- **Anulación**: `POST /{env}/anulacion/api/Anulacion`
- **Aprobación Comercial**: `POST /{env}/aprobacionComercial/api/AprobacionComercial`
- **RFCE (Consumo < 250k)**: Ojo, dominio distinto `https://fc.dgii.gov.do/rfcetest/api/RecepcionFC` (Test) o `https://fc.dgii.gov.do/rfce/api/Recepcion` (Prod).

## 6. Flujo de Autenticación
1. Hacer un GET al endpoint de Semilla. Devuelve un XML con una semilla string.
2. Extraer el string, firmar todo el XML de la semilla con el .p12.
3. POST al endpoint ValidarSemilla con el XML firmado.
4. DGII responde con un Bearer Token válido por 1 hora.

## 7. Flujo de Emisión Normal
1. Se construye el XML del e-CF según XSD.
2. Se firma el XML.
3. Se obtiene el Token.
4. POST a Recepción e-CF adjuntando el token.
5. DGII devuelve un `TrackId` (Guía).
6. Se realiza polling (Cron) al endpoint de Consulta Estado enviando RNC, e-NCF y TrackId.
7. Si el estado es `1` (Aceptado) o `4` (Condicional), el proceso finalizó con éxito en DGII. Si es `2` (Rechazado), hay que corregir. Si es `3` (En proceso), seguir consultando.
8. Enviar el XML a la "URL de Recepción" del cliente (ARECF).

## 8. Flujo RFCE (Resumen Factura Consumo)
Solo aplica para e-CF **E32 (Consumo)** cuyo Total sea `< RD$250,000**.
- **No se envía a Recepción Normal.** Se envía al endpoint RFCE.
- **Es Sincrónico**: La DGII valida en el momento y devuelve inmediatamente el estado final (Aprobado o Rechazado) junto al código de seguridad. No hay TrackId ni polling posterior.

## 9. Proceso de Certificación (14 Pasos Set de Pruebas)
Para habilitar Producción, se debe superar el ambiente CerteCF:
1. **Registro del Software**: Enviar en OFV la URL Recepción, Aprobación, Autenticación y datos del proveedor.
2. DGII entrega un Excel ("Hoja de Trabajo") con escenarios específicos.
3. **Orden estricto de envío**: 
   - 1ro: Los de primer nivel (E31, E32 >= 250k, E41, etc.)
   - 2do: Notas (E33, E34) que aplican a los enviados en el 1ro.
   - 3ro: Lote RFCE de notas de crédito.
   - 4to: Consumo RFCE (< 250k).
4. **Paso 5 Representación Impresa**: Subir un PDF < 10MB demostrando formato visual y QR.
5. **Paso 13 Declaración Jurada**: Enviar formulario firmado electrónicamente.

## 10. Representación Impresa (PDF)
Debe tener obligatoriamente:
- Título del comprobante (Ej. FACTURA DE CRÉDITO FISCAL ELECTRÓNICA).
- RNC, Razón Social, Dirección, Teléfono de Emisor y Comprador.
- e-NCF, Fecha de vencimiento de secuencia.
- Montos detallados, ITBIS e ISR si aplica.
- **Código de Seguridad**: Los primeros 6 caracteres del `SignatureValue` del XML firmado.
- **Código QR**: Debe apuntar a `https://ecf.dgii.gov.do/consulta?RNCEmisor=X&NCF=Y...` con todos los parámetros correctos.
- Leyenda: "Este documento es una representación impresa de un e-CF..."

## 11. Comunicación Emisor-Receptor (Aprobación Comercial)
- Los ERP deben exponer un endpoint REST (URL de Recepción) para que otros contribuyentes les envíen e-CF (E31, E33, E34).
- Al recibir, se envía a DGII un Acuse de Recibo (ARECF).
- Luego, contabilidad revisa y se envía a DGII una Aprobación Comercial (ACECF) indicando si Acepta o Rechaza la factura.

## 12. Errores Comunes
- **Signature is invalid**: Certificado vencido, .p12 mal exportado, o canonicalización incorrecta en el XMLDSig (espacios agregados después de firmar).
- **Error XSD / Formato**: Usar `#e` en vez de obviar el tag. Los tags vacíos o `<Tag></Tag>` fallan.
- **Secuencia agotada**: El ERP no lleva el control de vencimiento y tope de los e-NCF.
- **Cédula no válida**: Para E31, el comprador DEBE tener un RNC o Cédula registrado en DGII. Si no está en BD de DGII, falla.
- **Token expirado**: Se reusa el token después de 60 minutos.

Este Skill provee el contexto completo para responder, generar código o auditar implementaciones de e-CF DGII en cualquier lenguaje (C#, PHP, Node, etc.).
