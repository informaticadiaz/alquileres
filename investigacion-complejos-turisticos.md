# Investigación de complejos turísticos — intención y reglas

Fecha: 2026-09-30. Estado: **intención documentada; investigación no iniciada.**

## Objetivos

1. **Generar contenido** para Alquileres Temporarios (`alquileres.diazignacio.ar`):
   sumar complejos turísticos y departamentos temporarios a las categorías por
   destino, mediante anuncios con consentimiento y fichas de directorio con las
   condiciones de la sección "Decisiones tomadas".
2. **Prospectar**: identificar propietarios y administradores de complejos y
   contactarlos para invitarlos a publicar en la plataforma.

Ambos objetivos se realizan **cumpliendo la legislación argentina** y los
términos de uso de las fuentes consultadas.

## Principios

| Principio | Qué significa en la práctica |
| --- | --- |
| Consentimiento para publicar | Un anuncio completo sólo se publica si el propietario o administrador lo carga o autoriza expresamente su carga. Las fichas de directorio siguen sus propias condiciones. |
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

## Decisiones tomadas

### Fichas de directorio sin consentimiento previo — aprobadas (2026-09-30)

Además de los anuncios con consentimiento, se podrán publicar **fichas de
directorio** de complejos turísticos comerciales, siempre que cumplan **todas**
estas condiciones:

| Condición | Detalle |
| --- | --- |
| Sólo datos fácticos | Nombre comercial, localidad o destino, tipo de alojamiento y enlace a la web oficial del complejo. |
| Sin contenido ajeno | Sin fotos, descripciones, reseñas, precios ni disponibilidad copiados de ninguna fuente. |
| Carga manual desde la fuente propia | Cada ficha se arma dato por dato desde la web o red oficial del complejo; no se vuelcan listados de directorios ni plataformas. |
| Identificación visible | La ficha indica "Información pública, no gestionada por el complejo" y no sugiere relación comercial ni respaldo. |
| Sin intermediación | La ficha no ofrece formulario de consulta ni contacto a través del sitio; remite a la web oficial del complejo. |
| Sólo establecimientos comerciales | No se crean fichas de particulares (por ejemplo, un departamento en un domicilio o un teléfono personal). |
| Baja a pedido | Un enlace o dato de contacto permite pedir la baja; se elimina sin demora. |
| Registro de origen | En `data/prospeccion/` se registra la fuente y la fecha de consulta de cada ficha. |

Antes de publicar fichas en volumen, conviene una consulta breve con un abogado,
en especial sobre la presentación de la ficha (lealtad comercial y marcas) y los
datos de personas físicas.

Cuando el complejo acepta sumarse, la ficha se reemplaza por su anuncio
completo, cargado o autorizado por el propietario.

## Decisiones pendientes

- Cómo implementar las fichas de directorio en Osclass sin formulario de
  consulta ni precio (hoy todo anuncio muestra el contacto nativo).
- Canal de contacto (correo, formulario web, WhatsApp comercial) y texto del
  mensaje de invitación.
- Correo propio del sitio para prospección (hoy el correo de Osclass no está
  configurado).
