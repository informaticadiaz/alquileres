# Diagnóstico de UI — sitio actual

Etapa 1 del proceso de `rediseno-ui-con-ia.md`. Registra cómo se ve y se usa
hoy `https://alquileres.diazignacio.ar` antes del rediseño.

## Alcance y método

- **Fecha:** 2026-10-09.
- **Versión auditada:** la publicada. Se verificó que el dominio sirve sin
  caché (`cf-cache-status: DYNAMIC`) los archivos vigentes de `app/osclass`
  (tema `sigma` más plugins propios); Osclass no tiene paso de build.
- **Herramienta:** `../playwright/audit.mjs` con
  `../playwright/projects/alquileres.mjs` (sólo lectura).
- **Viewports:** escritorio 1440×900 y celular 390×844, captura de página
  completa.
- **Pantallas:** inicio, región (Córdoba), destino (Mina Clavero), ficha,
  publicar alojamiento, contacto y registro.
- **Evidencia:** capturas, `report.json` y `reporte.md` en
  `../playwright/data/alquileres/2026-10-09/` (no versionado).
- El chequeo automático de accesibilidad corre sólo en escritorio; los
  hallazgos de celular salen de la revisión visual.

## Hallazgos

Severidad: **alta** afecta la confianza o contradice el producto; **media**
dificulta el uso; **baja** es pulido.

### Contenido y confianza

| # | Severidad | Pantalla | Hallazgo |
|---|---|---|---|
| 1 | Alta | Ficha | El recuadro "Información útil" muestra consejos de clasificados genéricos (pagar con PayPal, no usar Western Union, no aceptar cheques del exterior, no comprar ni vender fuera del país). No corresponden a alojamientos y generan desconfianza. |
| 2 | Alta | Ficha, resultados, inicio | La descripción autogenerada tiene un error de concordancia ("es un Cabaña") y se repite idéntica en todas las tarjetas, por lo que no aporta información. |
| 3 | Alta | Inicio, resultados, ficha | Ninguna ficha tiene fotos, pero las tarjetas están construidas alrededor de la imagen: el inicio muestra 12 marcadores grises idénticos. |
| 4 | Media | Ficha, resultados | Ubicación duplicada: "Mina Clavero, Mina Clavero, Argentina" y "Mina Clavero (Valle de Traslasierra) / Mina Clavero (Mina Clavero)". |
| 5 | Media | Ficha | Contador público "N visitas a la página": con valores cercanos a cero desalienta en lugar de informar. |
| 6 | Media | Ficha | El aviso "no admite consultas directas" convive con el selector "Marcar como…" y con un formulario de contacto y uno de comentarios presentes en el HTML aunque no se muestren. |
| 7 | Media | Inicio | No explica qué es el sitio ni cómo funciona (vitrina, contacto directo, sin comisión); ese mensaje aparece sólo en el pie. |

### Navegación y búsqueda

| # | Severidad | Pantalla | Hallazgo |
|---|---|---|---|
| 8 | Media | Región, destino | Dos sistemas de filtros superpuestos: el lateral de Osclass (texto, ciudad, precio, "sólo con fotos", dormitorios, baños, "huéspedes máximos") y el del plugin (tipo, huéspedes mínimos, dormitorios mínimos). Varios campos no aplican a fichas sin precio ni fotos. |
| 9 | Media | Destino (celular) | El panel de filtros ocupa la primera pantalla completa; los resultados empiezan después de desplazarse. |
| 10 | Media | Inicio | Las regiones usan un ícono genérico de "compartir" que no representa nada, y el bloque "Todos los lugares" está vacío. |
| 11 | Baja | Región, destino | "Suscríbete a esta búsqueda" pide un correo para avisos sin explicar qué se recibe. |
| 12 | Baja | Resultados | Texto de conteo poco natural: "1 - 5 de los listados de 5". |

### Publicar alojamiento

| # | Severidad | Pantalla | Hallazgo |
|---|---|---|---|
| 13 | Media | Publicar | Formulario genérico de clasificados: pide precio, barrio, "Región" como texto libre y "Otro contacto", y no muestra los datos propios de un alojamiento (tipo, capacidad, dormitorios). |
| 14 | Media | Publicar | "Mostrar el teléfono en la página del anuncio" viene marcado por defecto. |

### Lenguaje y consistencia

| # | Severidad | Pantalla | Hallazgo |
|---|---|---|---|
| 15 | Media | Todas | Mezcla de voseo y tuteo: "Encontrá", "Buscá" frente a "Publica tu anuncio", "Elige una categoría", "Suscríbete". |
| 16 | Media | Todas | Textos de traducción incompletos o literales: "Regístrate en" (cortado), "Suscribirme!", "Ubicación de los anuncios", "El número de teléfono". |
| 17 | Baja | Todas | El selector de idioma ofrece "Spanish (Spain)" para un sitio argentino. |
| 18 | Baja | Varias | Se usa "anuncio" y "listado" para lo que el producto llama "ficha" o "alojamiento". |

### Diseño visual

| # | Severidad | Pantalla | Hallazgo |
|---|---|---|---|
| 19 | Media | Todas (escritorio) | El pie está roto: el aviso legal se parte en dos bloques desalineados y una parte sale del contenedor. |
| 20 | Baja | Ficha (celular) | Aparece un recuadro amarillo vacío debajo del aviso de la ficha. |
| 21 | Baja | Todas | Identidad genérica del tema: mezcla de serif y sans sin jerarquía clara, botones celestes y negros sin sistema, ícono de casa genérico. |

### Accesibilidad (chequeo automático, escritorio)

| # | Severidad | Hallazgo |
|---|---|---|
| 22 | Media | El celeste de enlaces y botones ("Iniciar sesión", "Buscar", "Aplicar filtros", "Enviar", migas de pan) tiene contraste 3,1:1; el mínimo para texto normal es 4,5:1. |
| 23 | Media | Campos sin etiqueta asociada en búsqueda, filtros, publicar y registro (26 casos en total). |
| 24 | Media | Enlaces sin nombre accesible: 32 miniaturas de tarjetas y el logo. |

## Aspectos positivos

- Sin errores de consola en ninguna pantalla.
- Diseño adaptable: menú colapsado en celular y sin desplazamiento horizontal.
- La etiqueta "Ficha informativa" y los enlaces "Visitar sitio oficial" y
  "Solicitar baja" ya comunican bien la naturaleza de las fichas.
- Las migas de pan reflejan la jerarquía región → destino → ficha.
- El aviso de vitrina está presente en todas las páginas.

## Relación con el prototipo v1

`prototipo-ui/v1` ya atiende los hallazgos 1, 2, 3, 4, 5, 6, 7, 10, 15, 18,
19 y 21. Quedan sin cubrir: filtros (8, 9), publicar (13, 14), textos de la
interfaz de Osclass (16, 17), suscripción (11), conteo (12), el recuadro vacío
(20) y la accesibilidad de los formularios (22–24).

## Próximo paso

Etapa 2: brief del rediseño (audiencia, tono, referencias y restricciones),
usando estos hallazgos como problemas a resolver.
