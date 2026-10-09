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
| `item-post.php` | Publicar o editar alojamiento: secciones El alojamiento, Fotos, Ubicación y Contacto; sin precio ni barrio; correo y teléfono ocultos por defecto en alojamientos nuevos; sin aviso PHP cuando la instancia no tiene países cargados. |
| `contact.php`, `user-register.php`, `user-login.php` | Formularios reescritos con etiquetas asociadas y voseo. |
| `user-*.php` | Páginas de cuenta con el marcado de Sigma y textos en voseo; el menú de usuario se renombró en `get_user_menu()`. |
| `index.php` | Metadatos del tema. |

Ninguna página usa la miga de pan genérica de Osclass (etiquetas en inglés);
resultados y ficha dibujan la suya. El menú de cuenta sale de `at_user_menu()`:
el núcleo de Osclass ya define `get_user_menu()`, así que la copia de Sigma
nunca se ejecuta. En celular el menú se muestra arriba del contenido.

Las páginas de cuenta se auditan con `../playwright/projects/alquileres-cuenta.mjs`
y el usuario de prueba `prueba-ui` (credenciales en
`data/osclass/usuario-prueba-ui.txt`, ignorado por Git), pasadas por
`AT_EMAIL` y `AT_PASSWORD`.

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
- Auditoría visual: `../playwright/projects/alquileres.mjs`; el 2026-10-09 las
  siete pantallas (inicio, región, destino, ficha, publicar, contacto,
  registro) dieron cero errores de consola, contraste, etiquetas y nombres
  accesibles en escritorio y celular.
