# Vidriera de alojamientos turísticos

Este plugin de Osclass agrega metadatos y filtros para publicar alojamientos turísticos. Es una vidriera con consultas: **no confirma reservas ni procesa pagos**.

## Activación

1. Copiar o enlazar esta carpeta dentro de `oc-content/plugins/`.
2. Activar **Tourist Accommodation Showcase** desde *Plugins*.
3. Abrir *Configure* y seleccionar las categorías que representan alojamientos turísticos.
4. Publicar anuncios de prueba en esas categorías y completar los campos que Osclass muestra en el formulario nativo de anuncio.

La activación crea campos de Osclass reutilizables, pero no crea categorías ni anuncios. La configuración vincula esos campos a las categorías elegidas.

## Despliegue

La entrada activa del plugin es
`app/osclass/oc-content/plugins/tourist-showcase.php`, no el directorio anidado
`tourist-showcase/`. Para desplegar esta fuente hay que copiar
`tourist-showcase.php` y `tourist-showcase-lib.php` a la raíz de
`oc-content/plugins/`, y `assets/` a
`oc-content/plugins/tourist-showcase/assets/`. Copiar sólo el directorio fuente
no actualiza el código activo del plugin.

## Comportamiento público

| Área | Comportamiento |
| --- | --- |
| Ficha | Osclass muestra tipo, capacidad, dormitorios, baños y comodidades mediante sus campos nativos. |
| Búsqueda | Permite filtrar por tipo, huéspedes mínimos y dormitorios mínimos. |
| Consulta | Conserva el botón y formulario de contacto nativos de la ficha de Osclass. |
| Idioma | La copia añadida usa español cuando el locale público es `es_ES`; en otros locales muestra inglés. |

## Límites explícitos

No implementa calendario, disponibilidad, bloqueo de fechas, reservas, cancelaciones, pagos, checkout ni almacenamiento de datos de huéspedes. El precio de Osclass sigue siendo un precio de anuncio; el plugin no lo interpreta ni lo convierte en una transacción.

## Verificación

```bash
php tests/test_tourist_showcase.php
php -l plugins/tourist-showcase/tourist-showcase.php
```

La prueba cubre los contratos puros del plugin. La comprobación de integración requiere activarlo desde el panel de Osclass, elegir categorías y verificar un anuncio de prueba: no se usa una base de huéspedes ni se generan anuncios de producción.
