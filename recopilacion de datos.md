Eres un experto en facturación electrónica de República Dominicana (DGII e-CF).

Necesito que generes un documento .md completo y detallado que cubra TODO 
el proceso de facturación electrónica dominicana, desde cero hasta producción.

El documento debe cubrir:

## 1. Marco Legal
- Ley 32-23
- Decreto 587-24  
- Norma General 01-2020
- Obligatoriedad y plazos

## 2. Requisitos Previos
- Inscripción RNC
- Acceso OFV
- Alta NCF
- Certificado digital (.p12) — cómo obtenerlo, quién lo emite, vigencia
- Delegación de roles (Firmante, Aprobador Comercial, Administrador)
- Formulario FI-GDF-016

## 3. Tipos de e-CF
Para cada tipo (31, 32, 33, 34, 41, 43, 44, 45, 46, 47):
- Nombre completo
- Cuándo se usa
- Campos obligatorios específicos
- Diferencias clave con otros tipos
- Reglas de negocio (ej: E34 requiere IndicadorNotaCredito, 
  E33/E34 referencian documento original, E32 < 250k va por RFCE)

## 4. Estructura Técnica del XML
- Estructura general ECF (Encabezado → IdDoc, Emisor, Comprador, Totales, Items)
- Firma digital: algoritmo SHA256, URI vacío, XMLDSig
- Reglas: no tags vacíos, no caracteres inválidos, codificación UTF-8
- Formato e-NCF: E + tipo (2 dígitos) + secuencia (10 dígitos)
- Formato fechas: dd-mm-yyyy
- Valor '#e' en el Excel oficial = campo vacío (no incluir en XML)

## 5. Ambientes y URLs
Tabla completa con los 3 ambientes:
- Pre-Certificación (TesteCF)
- Certificación (CerteCF)  
- Producción (eCF)

Para cada ambiente, los endpoints de:
- Autenticación (semilla + validar semilla)
- Recepción e-CF
- Consulta resultado (por TrackId)
- Recepción RFCE (dominio fc. diferente)
- Aprobación Comercial
- Anulación
- Directorio
- Estatus servicios

## 6. Flujo de Autenticación
- Obtener semilla (GET)
- Firmar semilla con certificado
- Validar semilla (POST con XML firmado)
- Recibir token (válido 1 hora)
- Persistencia del token

## 7. Flujo Completo de Emisión
Paso a paso desde que se crea la factura hasta que DGII la acepta:
1. Generar XML según tipo
2. Validar contra XSD oficial
3. Firmar digitalmente
4. Autenticar (token)
5. Enviar a DGII
6. Recibir TrackId
7. Consultar estado (polling)
8. Estados posibles: 0 No encontrado, 1 Aceptado, 2 Rechazado, 
   3 En proceso, 4 Aceptado condicional
9. Enviar al receptor (comunicación emisor-receptor)
10. Receptor envía Acuse de Recibo (ARECF)
11. Aprobación Comercial (ACECF) si aplica

## 8. Flujo RFCE (Resumen Consumo < RD$250,000)
- Cuándo aplica
- Diferencias con flujo normal
- Endpoint diferente (fc.dgii.gov.do)
- Respuesta: codigo, estado, encf, secuenciaUtilizada

## 9. Proceso de Certificación DGII (14 pasos)
Detalle completo de cada paso:
- Etapa 1: Solicitud
- Etapa 2: Set de pruebas (pasos 1-13)
  - Paso 1: Registro del software (campos requeridos: URL Recepción, 
    URL Aprobación, URL Autenticación, versión, datos proveedor)
  - Paso 2: Pruebas con Excel oficial de DGII
  - **Orden obligatorio**: 
    Primero: 31, 32(≥250k), 41, 43, 44, 45, 46, 47
    Segundo: 33, 34 (referencian docs del primero)
    Tercero: RFCE-32
    Cuarto: 32 < 250k
  - Paso 5: Representación Impresa (PDF < 10MB)
  - Paso 13: Declaración Jurada (XML firmado)
- Etapa 3: Certificación y habilitación

## 10. Representación Impresa (RI)
Campos obligatorios en el documento físico/PDF:
- e-NCF
- Fecha Vencimiento Secuencia
- Código de Seguridad
- Fecha Firma
- Código QR (URL de verificación DGII)
- Datos del emisor y comprador
- Desglose ITBIS por ítem
- Totales

## 11. Comunicación Emisor-Receptor
- Endpoints del receptor (URL Recepción, URL Aprobación)
- Directorio electrónico DGII
- Acuse de Recibo (ARECF): estados 0=Recibido, 1=No Recibido
- Motivos de no recibido (1=Error especificación, 2=Error firma, 
  3=Envío duplicado, 4=RNC no corresponde)
- Aprobación Comercial (ACECF): estados 1=Aceptado, 2=Rechazado

## 12. Anulación (ANECF)
- Cuándo anular
- Estructura del XML
- Rangos de secuencias
- Límite: 10 tipos por ANECF, 10,000 secuencias por tipo

## 13. Errores Comunes y Soluciones
Lista de los errores más frecuentes que devuelve DGII y cómo resolverlos:
- Errores de firma digital
- Errores de XSD
- Errores de RNC
- Errores de secuencia
- Errores de token expirado

## 14. Checklist Pre-Producción
Lista completa de verificación antes de ir a producción.

## 15. Lo que normalmente se olvida o causa problemas
Basado en tu experiencia, lista los puntos que los desarrolladores 
frecuentemente pasan por alto o que causan rechazos en DGII.

---

Formato: Markdown completo, con tablas donde aplique, ejemplos de 
XML mínimos para los tipos más comunes (E31, E32, E34), y ejemplos 
de respuestas de DGII.

Sé exhaustivo. Este documento será la referencia técnica definitiva 
para un sistema ERP en producción.