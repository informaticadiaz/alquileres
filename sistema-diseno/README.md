# Sistema de diseño

Fuentes del sistema de diseño del rediseño (etapa 3 de
`../rediseno-ui-con-ia.md`), derivado de `../prototipo-ui/v1` y de
`../brief-ui.md`.

- **Publicado (privado):** https://claude.ai/artifact/GuBpcLM7edVS6Ar5wkCqNE
- **Formato:** archivos del tipo de Artifact *Design System*. El texto técnico
  está en inglés; los ejemplos de interfaz, en español.

## Contenido

| Archivo | Qué es |
|---|---|
| `brand-book.md` | Guía de marca: tono, voseo, vocabulario, color, tipografía, estructura de páginas. En el Artifact se publica como `project/README.md`. |
| `tokens.json` | Colores (tema claro), tipografía Archivo, espaciados, radios y tamaños. |
| `components/bundle.css` | Clases CSS de los componentes, con prefijo `at-`; sin JavaScript. |
| `components/<Componente>/` | Guía de uso (`README.md`) y vista previa (`preview.html`) de 12 componentes. |
| `components/Cover/preview.html` | Portada del sistema. |
| `design-system.json` | Índice del Artifact. |

## Decisiones

- Un solo tema (claro); el sitio no tiene modo oscuro.
- Componentes en HTML y CSS, para trasladarlos al tema PHP de Osclass.
- Borde de controles `border-control` `#7a8581` (3,8:1); reemplaza el
  `#A9B2AE` del prototipo v1, que no llegaba al 3:1.
- El filtro por tipo (`FilterChip`) reemplaza al panel lateral de Osclass.

## Pendientes

- Íconos y logo (hoy el nombre va en tipografía).
- Paginación, estado sin resultados y mensajes de error de formularios.

## Actualizar el Artifact

Editar estos archivos y volver a publicarlos al mismo Artifact bajo
`project/<ruta>`, con `brand-book.md` como `project/README.md`. Los cambios
hechos desde la página del Artifact hay que traerlos de vuelta a esta carpeta.
