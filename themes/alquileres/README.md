# Tema `alquileres`

Tema de Osclass del rediseño de UI (etapa 5 de `../../rediseno-ui-con-ia.md`).
Parte de una copia de Sigma 1.7.0 y aplica `../../sistema-diseno/`.

## Qué cambia respecto de Sigma

| Archivo | Cambio |
|---|---|
| `css/alquileres.css` | Tokens y componentes del sistema de diseño; se carga después de `style.css` y `responsive.css` y anula lo que choca. |
| `head.php` | Carga Archivo (Google Fonts, eje `wdth`) y `alquileres.css`. |
| `header.php` | Cabecera con el nombre del sitio solamente; sin buscador ni menú. Las páginas rediseñadas dibujan sus propias migas de pan. |
| `footer.php` | Aviso de vitrina (lo imprime `tourist-identity` en el hook `footer`) y navegación: Destinos, Cómo funciona, Ingresar, Publicar alojamiento. Sin selector de idioma. |
| `main.php` | Inicio: título, tablero de regiones, recién sumados, cómo funciona y banda para propietarios, generados con datos de Osclass. |
| `search.php` | Banda del color de la región con migas y título, filtros de `tourist-showcase`, filas de alojamiento y paginación. Sin el panel lateral de Sigma. |
| `item.php` | Ficha: datos, panel de acción (aviso de `tourist-directory` o contacto del propietario) y más alojamientos del destino. Sin consejos de clasificados, contador de visitas, "Marcar como…" ni comentarios. |
| `functions.php` | Funciones `at_*` (color de región, nombres cortos, ruta de categorías, fila de alojamiento) y quita la grilla de últimos anuncios de Sigma. |
| `index.php` | Metadatos del tema. |

Las demás plantillas (publicar, contacto, registro, cuenta) siguen siendo las de
Sigma con los estilos generales del tema.

## Despliegue

La fuente es esta carpeta. Para publicarla:

```sh
cp -r themes/alquileres app/osclass/oc-content/themes/
diff -r themes/alquileres app/osclass/oc-content/themes/alquileres
```

No hay paso de build: el servidor PHP sirve los archivos al instante.

## Activar y volver atrás

El tema activo es la preferencia `theme` de la sección `osclass`. Desde
`app/osclass`:

```sh
php -r '$_SERVER["HTTP_HOST"]="alquileres.diazignacio.ar"; $_SERVER["REQUEST_URI"]="/"; $_SERVER["SERVER_NAME"]="alquileres.diazignacio.ar"; require "oc-load.php"; osc_set_preference("theme", "alquileres", "osclass");'
```

Para volver a Sigma, el mismo comando con `"sigma"`. Sigma queda intacto en
`oc-content/themes/sigma/`.

## Verificación

- `php -l` sobre las plantillas modificadas.
- `php tests/test_tourist_showcase.php` desde la raíz del repo.
- Auditoría visual: `../playwright/projects/alquileres.mjs` (inicio, región,
  destino y ficha sin errores de consola, contraste, etiquetas ni nombres
  accesibles el 2026-10-09).
