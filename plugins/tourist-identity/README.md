# Identidad del portal de alquileres temporarios

Este plugin de Osclass transforma una instalación genérica en el portal
**Alquileres Temporarios**: título y descripción del sitio, copy público,
logo, un árbol de categorías de destinos turísticos (6 regiones y 51
destinos), moneda ARS exclusiva y limpieza de los anuncios de prueba
sembrados por la instalación base. La operación es **idempotente** (volver a
ejecutarla no cambia nada que ya esté correcto) y **reversible** (desinstalar
restaura el estado previo, con la única excepción de los dos anuncios
eliminados).

## Activación

1. Copiar esta carpeta dentro de `oc-content/plugins/tourist-identity/`
   (ver "Re-sincronizar antes de activar" más abajo).
2. Activar **Tourist Portal Identity** desde *Plugins*. La instalación:
   - guarda un snapshot de los valores previos en la sección de preferencias
     `tourist_identity` (una sola vez, nunca se sobreescribe);
   - aplica el plan de identidad (ver "Qué cambia").
3. Si se necesita reaplicar después de un cambio manual, de actualizar el
   código de este plugin o de reinstalar `tourist-showcase`, abrir
   *Configure* y usar el botón **Re-apply**. La reaplicación es segura de
   repetir: sólo toca los valores que todavía difieren del objetivo y
   reporta cuántos cambios hizo, tanto en el mensaje de la pantalla
   (mensajes flash no son visibles de forma confiable en `action=admin`)
   como de forma persistente si se recarga la pantalla sin reaplicar.

## Árbol de destinos (6 regiones, 51 destinos)

La categoría 47 ("Alquiler Vacacional" en la versión 1.0.0) se repropone en
el lugar como la región **Buenos Aires**: conserva su id y su posición en el
árbol, sólo cambian su nombre, descripción y slug. Las otras 5 regiones
(Córdoba, Cuyo, Litoral, Norte, Patagonia) y los 51 destinos (hoja) se crean
como categorías nuevas la primera vez que se aplica el árbol. Cada región y
cada destino incluye un destino catch-all "Otros destinos de…" al final.

El árbol completo (claves, nombres es_ES/en_US y orden) vive en
`tourist-identity-tree.php`. Los ids de las filas creadas se registran en la
preferencia `tourist_identity.category_map` (JSON `{clave: id}`) para que
volver a aplicar el árbol nunca cree filas duplicadas: cada clave se
resuelve contra el mapa antes de insertar, y si un id mapeado ya no existe en
la base (por ejemplo, se borró a mano) se vuelve a insertar bajo la misma
clave.

Las descripciones (nombre, descripción, slug) sólo se escriben para los
locales instalados que el árbol conoce (es_ES y en_US); si el sitio tiene
instalado sólo uno de los dos, sólo ese recibe copy del árbol. Cualquier otro
locale instalado que no sea es_ES/en_US no se toca.

## Qué cambia

| Área | Resultado |
| --- | --- |
| Preferencias core | `pageTitle` = "Alquileres Temporarios"; `pageDesc` con la descripción en es_ES; `currency` = `ARS`. |
| Preferencias sigma | `keyword_placeholder` con el placeholder de búsqueda en es_ES; `footer_link` = `0` (oculta "Powered by Osclass"); `logo` apunta al SVG instalado. |
| Categorías | La categoría 47 se repropone como región "Buenos Aires" (nivel superior, `fk_i_parent_id = NULL`); las otras 5 regiones y los 51 destinos se crean habilitados bajo su región; las 94 categorías de ejemplo originales quedan deshabilitadas (`b_enabled = 0`), sin tocar las filas propias del árbol. |
| Moneda | Se inserta la fila `ARS` (si falta) y se habilita; `USD`, `EUR` y `GBP` quedan deshabilitadas. La lista `currencies_*` (sin filtrar) sigue intacta, así los precios ya cargados siguen formateándose. |
| Vínculo con `tourist-showcase` | Si ese plugin está activo, se llama a `tourist_showcase_save_categories()` con los 51 ids de destinos (hoja), nunca con los ids de región, para mantener sus cinco campos ligados exactamente a los destinos publicables. |
| Anuncios semilla | Se eliminan los anuncios 1 y 2 mediante el gestor nativo de anuncios (irreversible). |
| Copy público | Los textos del héroe, el CTA de publicar y "Últimos anuncios" se reemplazan sólo en páginas públicas, nunca en `oc-admin`. |
| Pie de página | Se agrega el descargo de responsabilidad (es_ES/en_US) sólo en páginas públicas. |

## Actualizar desde la versión 1.0.0 (sitio ya instalado)

Osclass no dispara un hook de actualización de versión cuando cambia el
código de un plugin ya activo: sólo existen los hooks de instalación,
activación, desactivación, desinstalación y *Configure*. Por eso, para pasar
un sitio que ya tiene este plugin en 1.0.0 (categoría 47 única, sin árbol) a
esta versión con el árbol de destinos, el procedimiento es:

1. Re-sincronizar el código fuente (ver más abajo) para que Osclass ejecute
   esta versión.
2. Abrir *Configure* y presionar **Re-apply** una vez: crea las 5 regiones y
   los 51 destinos nuevos, repropone la categoría 47 como "Buenos Aires" (y
   antes de tocarla, guarda un snapshot adicional —
   `tourist_identity.snapshot_tree`— con su nombre, descripción y posición
   previos en es_ES/en_US, ya que el snapshot original de la versión 1.0.0
   no lo tenía) y re-liga `tourist-showcase` a los 51 destinos. El resultado
   inline debe mostrar una cantidad de cambios mayor a cero.
3. Presionar **Re-apply** una segunda vez: al no quedar nada por crear ni
   actualizar, el resultado inline debe leer "Re-apply complete: 0
   change(s).", confirmando que la reaplicación es idempotente.

## Re-sincronizar antes de activar

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

Desinstalar restaura, desde el snapshot original: `pageTitle`, `pageDesc`,
`currency`, `keyword_placeholder`, `footer_link`, el estado
(`b_enabled`/padre) de las 95 categorías que existían antes de la primera
instalación (la 47 y las 94 de ejemplo) y las banderas/moneda por defecto
originales. La fila `ARS` que este plugin insertó queda presente pero
deshabilitada (no se borra, para no romper anuncios ya publicados en esa
moneda). El logo propio se elimina de `oc-content/uploads/`. Los anuncios 1
y 2 eliminados **no se recrean**: es la única parte irreversible del
proceso, aceptada por diseño.

Además, específicamente para el árbol de destinos:

- Cada región y cada destino que el plugin creó se **elimina** si no tiene
  ningún anuncio propio, o se **deshabilita** (nunca se elimina) si todavía
  tiene anuncios, conservando su id en `tourist_identity.category_map` para
  poder reutilizarlo si el plugin se vuelve a activar. Las eliminaciones se
  ejecutan siempre de hoja a región, nunca al revés, para no depender del
  borrado en cascada nativo de Osclass sobre filas que la desinstalación no
  decidió borrar.
- Si `tourist-showcase` está activo, todo id eliminado en este paso se quita
  también de `tourist_showcase.category_ids`, para que ese plugin nunca
  quede referenciando una categoría que ya no existe.
- La categoría 47 recupera su nombre, descripción, slug y posición
  anteriores al árbol desde la preferencia suplementaria
  `tourist_identity.snapshot_tree` (su `b_enabled` y su padre ya vuelven por
  el snapshot original). Ambas preferencias (`snapshot_tree` y
  `category_map`) se eliminan al finalizar.

## Límites explícitos

No gestiona reservas, pagos, disponibilidad ni checkout: sólo identidad de
marca, taxonomía y moneda del catálogo público. No modifica el código de
Osclass en `app/osclass/`; toda decisión pura vive en
`tourist-identity-lib.php` y en los datos de `tourist-identity-tree.php`, y
sólo `index.php` llama a la API de Osclass.

## Verificación

```bash
php tests/test_tourist_showcase.php
php -l plugins/tourist-identity/index.php
php -l plugins/tourist-identity/tourist-identity-lib.php
php -l plugins/tourist-identity/tourist-identity-tree.php
```

La prueba automática cubre únicamente los contratos puros (árbol de
destinos, mapa de reemplazos, plan de categorías/moneda/preferencias/árbol,
snapshot/restore, resolución de slugs, plan de desinstalación,
descargo de responsabilidad). La verificación de instalación, reaplicación y
desinstalación requiere activar el plugin desde el panel de Osclass y
comprobar el sitio público por HTTPS: título, meta descripción, H1, CTA,
placeholder de búsqueda, logo, descargo de responsabilidad, ausencia de
"Powered by Osclass", las 6 regiones en orden con sus 51 destinos (catch-all
al final de cada región), formulario de publicación que sólo ofrece destinos
(hoja) como categoría, campos de `tourist-showcase` visibles en un destino,
moneda ARS como única opción, anuncios 1 y 2 devolviendo 404 o 410 (Osclass
devuelve 410 Gone para anuncios eliminados), y páginas de `oc-admin` sin
ninguno de estos cambios de copy público.
