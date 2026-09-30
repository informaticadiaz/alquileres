# Investigación de complejos turísticos — intención y reglas

Fecha: 2026-09-30. Estado: **intención documentada; investigación no iniciada.**

## Objetivos

1. **Generar contenido** para Alquileres Temporarios (`alquileres.diazignacio.ar`):
   sumar complejos turísticos y departamentos temporarios a las categorías por
   destino.
2. **Prospectar**: identificar propietarios y administradores de complejos y
   contactarlos para invitarlos a publicar en la plataforma.

Ambos objetivos se realizan **cumpliendo la legislación argentina** y los
términos de uso de las fuentes consultadas.

## Principios

| Principio | Qué significa en la práctica |
| --- | --- |
| Consentimiento para publicar | Un anuncio sólo se publica si el propietario o administrador lo carga o autoriza expresamente su carga. |
| Sin copia de contenido ajeno | No se copian fotos, descripciones, reseñas ni precios de otros sitios o plataformas. |
| Sin extracción automatizada prohibida | No se hace *scraping* de plataformas cuyos términos lo prohíben (por ejemplo, portales de reservas y mapas comerciales). |
| Datos mínimos y públicos | Para prospectar sólo se registran datos de contacto comercial publicados por el propio complejo, y sólo los necesarios. |
| Transparencia | Todo contacto identifica al sitio, explica por qué se lo contacta y ofrece no volver a contactar. |
| Baja a pedido | Cualquier complejo contactado puede pedir que se eliminen sus datos; se elimina sin demora. |

## Marco legal a respetar

Referencias a verificar con asesoramiento antes de contactar a terceros:

- **Ley 25.326 de Protección de Datos Personales** (autoridad: Agencia de Acceso
  a la Información Pública): finalidad, calidad y minimización de los datos,
  derechos de acceso, rectificación y supresión.
- **Ley 26.951 — Registro Nacional "No Llame"**: consultar antes de cualquier
  contacto telefónico con fines comerciales.
- **Ley 11.723 de Propiedad Intelectual**: fotos y textos de terceros no se
  reutilizan sin autorización.
- **Ley 24.240 de Defensa del Consumidor**: la información publicada no debe
  inducir a error sobre el alojamiento, su precio o su disponibilidad.
- Normativa provincial o municipal de alquiler temporario o turístico (por
  ejemplo, el registro RAT de CABA, Ley 6255): se relevará por destino.

## Fuentes admitidas (a relevar)

- Registros oficiales y públicos de alojamientos turísticos provinciales y
  municipales.
- Directorios de oficinas de turismo, cámaras y asociaciones hoteleras.
- Sitios web y redes sociales **propios** de cada complejo (sólo para datos de
  contacto comercial publicados por el propio complejo).

## Circuito previsto

1. **Relevar fuentes** por región y destino (las 6 regiones y 51 destinos del sitio).
2. **Armar fichas de prospección** con nombre, destino, tipo, web propia y canal
   de contacto comercial público, más la fuente y la fecha de consulta.
3. **Contactar** con un mensaje transparente y la opción de no ser contactado nuevamente.
4. **Alta del anuncio**: el propietario publica, o autoriza por escrito una carga
   asistida con su propio material.
5. **Registrar** consentimiento, bajas y respuestas.

## Resguardo de datos

- Las fichas y registros de contacto son datos de terceros: se guardan
  **exclusivamente** en `data/prospeccion/`, ignorado por Git, nunca en
  documentación versionada ni en repositorios públicos.
- En este documento y en `CODEX_STATE.md` sólo se registran decisiones, fuentes
  genéricas y avances agregados, nunca datos personales.

## Decisiones pendientes

- Si se publicarán, además de anuncios con consentimiento, **fichas de
  directorio** sin consentimiento previo (sólo nombre, destino y enlace a la web
  propia, identificadas como "no gestionadas por el propietario" y con baja a
  pedido). Por defecto: **no**, hasta decidirlo expresamente.
- Canal de contacto (correo, formulario web, WhatsApp comercial) y texto del
  mensaje de invitación.
- Correo propio del sitio para prospección (hoy el correo de Osclass no está
  configurado).
