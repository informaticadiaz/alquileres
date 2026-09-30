# Esquema del seed de complejos turísticos

Fuente única de datos para los dos objetivos de
`investigacion-complejos-turisticos.md`: el **catálogo** (fichas de directorio y
anuncios) y la **prospección** (invitación a propietarios).

- Archivo: `data/prospeccion/seed/complejos.csv` (UTF-8, separador coma, una fila
  por complejo). Está ignorado por Git porque contiene contactos de terceros.
- Este esquema sí se versiona: no contiene datos de terceros.

## Campos

| Campo | Grupo | Obligatorio | Valores / formato | Uso |
| --- | --- | --- | --- | --- |
| `id` | Control | Sí | `<slug-nombre>--<slug-localidad>`, ASCII en minúsculas | Clave estable de la fila. |
| `nombre` | Público | Sí | Nombre comercial tal como lo presenta el complejo | Catálogo y prospección. |
| `localidad` | Público | Sí | Localidad real (por ejemplo, Mar Azul) | Catálogo. |
| `destino` | Público | Sí | Clave de destino del sitio (`tourist-identity-tree.php`), por ejemplo `costa-atlantica-villa-gesell-mar-de-las-pampas` | Categoría donde se publicaría. |
| `tipo` | Público | Sí | `apart_hotel`, `cabanas`, `complejo_departamentos`, `departamentos_con_servicios` | Filtro "Tipo de alojamiento". |
| `web` | Público | No | URL final del sitio propio (no portales de terceros) | Único enlace de la ficha de directorio. |
| `fuente` | Control | Sí | URL donde se descubrió el complejo | Registro de origen. |
| `fecha_consulta` | Control | Sí | `AAAA-MM-DD` | Registro de origen. |
| `estado_catalogo` | Estado | Sí | `candidato`, `sin_web`, `publicado`, `anuncio_propio`, `baja` | Flujo del catálogo. |
| `contacto_canal` | Privado | Sí | `email`, `formulario`, `whatsapp`, `ninguno` | Prospección. |
| `contacto` | Privado | No | Contacto comercial público del complejo | Prospección. **Nunca se publica.** |
| `estado_prospecto` | Estado | Sí | `pendiente`, `contactado`, `respondio`, `sumado`, `no_contactar`, `sin_contacto` | Flujo de prospección. |
| `fecha_ultimo_contacto` | Privado | No | `AAAA-MM-DD` | Evitar insistir. |
| `notas` | Control | No | Texto breve, sin datos personales | Observaciones. |

## Reglas

1. **Vistas, no copias.** La lista de catálogo y la de prospección se derivan
   del mismo archivo; no se mantienen por separado.
2. **Campos públicos.** Sólo `nombre`, `localidad`, `destino`, `tipo` y `web` pueden
   publicarse en una ficha de directorio; nunca `contacto`, `notas` ni los estados.
3. **Publicable como ficha:** `estado_catalogo = candidato` con `web` propia
   verificada. Sin web propia → `sin_web` (no se publica ficha).
4. **Baja:** un pedido de baja pasa `estado_catalogo` a `baja` y
   `estado_prospecto` a `no_contactar` en la misma fila.
5. **No contactar:** `estado_prospecto = no_contactar` bloquea todo nuevo
   contacto, aunque la ficha siga publicada si el complejo no pidió la baja.
6. **Sumado:** cuando el complejo publica su anuncio, `estado_prospecto = sumado`
   y `estado_catalogo = anuncio_propio`; la ficha de directorio se retira.
7. **Sin contenido ajeno:** el seed no guarda fotos, descripciones, precios,
   reseñas, disponibilidad, nombres de titulares ni teléfonos personales.
