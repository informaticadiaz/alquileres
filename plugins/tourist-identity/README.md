# Identidad del portal de alquileres temporarios

Este plugin de Osclass transforma una instalación genérica en el portal
**Alquileres Temporarios**: título y descripción del sitio, copy público,
logo, una única categoría de alta, moneda ARS exclusiva y limpieza de los
anuncios de prueba sembrados por la instalación base. La operación es
**idempotente** (volver a ejecutarla no cambia nada que ya esté correcto) y
**reversible** (desinstalar restaura el estado previo, con la única excepción
de los dos anuncios eliminados).

## Activación

1. Copiar esta carpeta dentro de `oc-content/plugins/tourist-identity/`
   (ver "Re-sincronizar antes de activar" más abajo).
2. Activar **Tourist Portal Identity** desde *Plugins*. La instalación:
   - guarda un snapshot de los valores previos en la sección de preferencias
     `tourist_identity` (una sola vez, nunca se sobreescribe);
   - aplica el plan de identidad (ver "Qué cambia").
3. Si se necesita reaplicar después de un cambio manual o de reinstalar
   `tourist-showcase`, abrir *Configure* y usar el botón **Re-apply**. La
   reaplicación es segura de repetir: sólo toca los valores que todavía
   difieren del objetivo y reporta cuántos cambios hizo.

## Qué cambia

| Área | Resultado |
| --- | --- |
| Preferencias core | `pageTitle` = "Alquileres Temporarios"; `pageDesc` con la descripción en es_ES; `currency` = `ARS`. |
| Preferencias sigma | `keyword_placeholder` con el placeholder de búsqueda en es_ES; `footer_link` = `0` (oculta "Powered by Osclass"); `logo` apunta al SVG instalado. |
| Categorías | Sólo la categoría 47 queda habilitada y pasa a nivel superior (`fk_i_parent_id = NULL`); todas las demás quedan deshabilitadas (`b_enabled = 0`). |
| Moneda | Se inserta la fila `ARS` (si falta) y se habilita; `USD`, `EUR` y `GBP` quedan deshabilitadas. La lista `currencies_*` (sin filtrar) sigue intacta, así los precios ya cargados siguen formateándose. |
| Vínculo con `tourist-showcase` | Si ese plugin está activo, se llama a `tourist_showcase_save_categories(array(47))` para mantener sus cinco campos ligados a la categoría 47. |
| Anuncios semilla | Se eliminan los anuncios 1 y 2 mediante el gestor nativo de anuncios (irreversible). |
| Copy público | Los textos del héroe, el CTA de publicar y "Últimos anuncios" se reemplazan sólo en páginas públicas, nunca en `oc-admin`. |
| Pie de página | Se agrega el descargo de responsabilidad (es_ES/en_US) sólo en páginas públicas. |

## Textos sobrescritos (revisar tras cada actualización de Osclass)

El filtro `gettext` reemplaza únicamente coincidencias exactas del texto ya
traducido (Osclass no expone clave ni dominio al filtro). Si una futura
actualización del tema o del núcleo cambia alguno de estos textos de origen,
la coincidencia deja de producirse silenciosamente y hay que actualizar el
mapa en `tourist-identity-lib.php` (`tourist_identity_override_map()`):

- es_ES: `¿Qué estás buscando hoy?` → `Encontrá tu alojamiento temporario en Argentina`
- es_ES: `Publicar anuncio` → `Publicar alojamiento`
- es_ES: `Últimos anuncios` → `Últimos alojamientos`
- en_US: `What are you looking for today?` → `Find your short-term rental in Argentina`
- en_US: `Publish Ad` → `Publish your listing`
- en_US: `Latest Listings` → `Latest listings`
- en_US: `Buscá por ciudad, provincia o tipo de alojamiento` → `Search by city, province or property type`

## Re-sincronizar antes de activar

El código fuente vive en `plugins/tourist-identity/` dentro de este
repositorio. La copia que Osclass realmente ejecuta está en
`app/osclass/oc-content/plugins/tourist-identity/`, y **nunca se edita
directamente ahí**. Antes de (re)activar o de disparar "Re-apply" tras un
cambio de código:

```bash
cp -r plugins/tourist-identity/ app/osclass/oc-content/plugins/tourist-identity/
diff -r plugins/tourist-identity/ app/osclass/oc-content/plugins/tourist-identity/
```

`diff -r` sin salida confirma que la copia desplegada es idéntica byte a byte
a la fuente. Este paso de despliegue requiere autorización explícita y no se
ejecuta automáticamente.

## Desinstalación

Desinstalar restaura, desde el snapshot: `pageTitle`, `pageDesc`, `currency`,
`keyword_placeholder`, `footer_link`, el estado (`b_enabled`/padre) de todas
las categorías y las banderas/moneda por defecto originales. La fila `ARS`
que este plugin insertó queda presente pero deshabilitada (no se borra, para
no romper anuncios ya publicados en esa moneda). El logo propio se elimina de
`oc-content/uploads/`. Los anuncios 1 y 2 eliminados **no se recrean**: es la
única parte irreversible del proceso, aceptada por diseño.

## Límites explícitos

No gestiona reservas, pagos, disponibilidad ni checkout: sólo identidad de
marca, taxonomía y moneda del catálogo público. No modifica el código de
Osclass en `app/osclass/`; toda decisión pura vive en
`tourist-identity-lib.php` y sólo `index.php` llama a la API de Osclass.

## Verificación

```bash
php tests/test_tourist_showcase.php
php -l plugins/tourist-identity/index.php
php -l plugins/tourist-identity/tourist-identity-lib.php
```

La prueba automática cubre únicamente los contratos puros (mapa de
reemplazos, plan de categorías/moneda/preferencias, snapshot/restore,
descargo de responsabilidad). La verificación de instalación, reaplicación y
desinstalación requiere activar el plugin desde el panel de Osclass y
comprobar el sitio público por HTTPS: título, meta descripción, H1, CTA,
placeholder de búsqueda, logo, descargo de responsabilidad, ausencia de
"Powered by Osclass", una sola categoría visible, formulario de publicación
con ARS como única moneda, anuncios 1 y 2 devolviendo 404, y páginas de
`oc-admin` sin ninguno de estos cambios.
