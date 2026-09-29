# Instalación mínima local de Osclass

## Decisión

Se adopta Osclass **8.3.1** como marketplace mínimo en
`https://alquileres.diazignacio.ar`. Se reutiliza exclusivamente el hostname y
la ruta remota de Cloudflare Tunnel ya existentes, reemplazando el origen
retirado de FewohBee en el puerto local `127.0.0.1:8783`.

## Alcance de esta etapa

- Código oficial extraído en `app/osclass`.
- Base local `osclass_local`, con usuario exclusivo `osclass_local`.
- Servicio de usuario `osclass.service`, habilitado y limitado a
  `127.0.0.1:8783`; Cloudflare Tunnel es el único ingreso público.
- `WEB_PATH` configurado como `https://alquileres.diazignacio.ar/`, para que
  enlaces y redirecciones no expongan el origen local.

Quedan fuera de alcance: Nginx/PHP-FPM, cambios de DNS o de configuración
remota del túnel, correo real, plugins, temas, ubicaciones, cron y datos de
producción.

## Fundamento verificado

La fuente oficial de Osclass indica que la versión estable actual es 8.3.1 y
que una instalación básica no requiere Composer ni Node. La documentación
oficial pide PHP 7.4 o posterior, MySQL/MariaDB y las extensiones `mysqli`,
`gd`, `curl`, `openssl`, `zip` y `mbstring`.

En este servidor se verificó PHP 8.5.4 con esas extensiones y MySQL 8.4.11.
El asistente web de Osclass confirmó que todos los requisitos de la aplicación
se cumplen al atenderlo temporalmente sólo en `127.0.0.1`.

Fuentes oficiales:

- <https://github.com/osclass-classifieds/Osclass>
- <https://docs.osclass-classifieds.com/minimum-requirements-i3>
- <https://docs.osclass-classifieds.com/osclass-installation-i4>

## Estado de instalación

| Componente | Estado | Evidencia |
|---|---|---|
| Código | Instalado | `app/osclass`; marcadores internos `8.3.1` |
| Requisitos PHP | Conforme | comprobación del instalador superada |
| Base y usuario MySQL | Verificados | conexión como `osclass_local@localhost` al esquema `osclass_local` |
| Instalador funcional | Completado | marcador `oc_t_preference.osclass_installed = 1` |
| Servicio y exposición pública | Activos | `osclass.service` en loopback y hostname remoto existente |

La verificación de privilegios devolvió únicamente `USAGE` global y `ALL
PRIVILEGES` sobre `osclass_local.*`. No hay privilegios sobre otros esquemas.
La cuenta puede operar todo el esquema propio porque el instalador necesita
crear su estructura; no puede crear ni administrar bases ajenas.

## Seguridad y credenciales

Las contraseñas aleatorias para la base y el administrador fueron generadas en
`data/osclass/credentials.env`, con permisos `0600`. El archivo de
configuración que el instalador creó (`app/osclass/config.php`) quedó también
con modo `0600`. `app/` y `data/` están ignorados por Git. Ninguno de estos
archivos se debe copiar a documentación, commits ni mensajes.

## Publicación y verificación

El 2026-09-29 se verificó que `alquileres.diazignacio.ar` seguía resolviendo
a Cloudflare pero respondía `502`: el Public Hostname remoto sobrevivió al
retiro de FewohBee y ya no existía un proceso en `127.0.0.1:8783`. No hubo
conflicto de hostname ni se cambiaron DNS, Cloudflare ni el túnel.

Se habilitó `osclass.service` para servir Osclass en ese mismo origen. La
comprobación posterior confirmó `/` por HTTPS con `200` y el título de Osclass.
`/oc-admin/` devuelve `302` a su inicio de sesión bajo el mismo hostname HTTPS,
sin redirección a `http://` ni a `127.0.0.1`.

## Limitaciones conocidas

- El servicio usa el servidor incorporado de PHP porque es el mecanismo mínimo
  ya utilizado por el origen reemplazado. Es adecuado para la validación
  inicial, no sustituye Nginx/PHP-FPM antes de una operación con tráfico real.
- El correo queda sin configurar. Se usó una dirección local no entregable en
  el asistente y su intento de envío no fue exitoso.
- No se importaron ubicaciones, no se configuraron tareas programadas y no se
  instalaron plugins ni temas adicionales.

## Paquete de idioma español

Existe un paquete **es_ES — Spanish (Spain)** para Osclass 8.3.1. El directorio
de traducciones de Osclass lo lista como compatible con **v8.3.1 o posterior**,
actualizado el 2026-01-06 y como uno de los paquetes listos para usar (80 % o
más completos). Es distribuido desde `osclass-classifieds.com`, el sitio cuyo
proyecto y marcas son operados por OsclassPoint/AB profitrade, s.r.o.; no es una
traducción atribuible al núcleo por mera existencia del enlace en el panel.

Se descargó, inspeccionó e instaló mediante el controlador nativo de idiomas el
archivo oficial `20260106_lang_osclass_es_ES_8.3.1.zip`. Su SHA-256 es
`cba8ce0bc7ce15a3fde39e54f750943df7e8fa45b5ffda5949eef2dc34ee4eb8`; contiene
los catálogos `core`, `messages` y `theme` en formatos `.po` y `.mo`, el
metadato de locale y las plantillas de correo. El propio `es_ES/index.php`
declara versión `8.3.1`, autor `Osclass` y licencia **GNU AGPL v3 o posterior**.
Esa licencia del paquete debe conservarse y no debe confundirse con la Apache
2.0 declarada para el núcleo actual de Osclass.

La vía nativa verificada es *International > Languages*: cargar el ZIP y dejar
que el controlador de Osclass lo descomprima y registre. La descarga automática
del Market requiere una API key de OsclassPoint, ausente en esta instalación;
por eso el ZIP oficial es el equivalente documentado por el propio directorio.

**Estado verificado:** Osclass confirmó “The language has been installed
correctly”. El paquete instalado conserva su identidad `es_ES`, versión 8.3.1,
y sus nueve archivos esperados. Posteriormente se habilitó únicamente para el
front office mediante la acción nativa “Enable in Front-office”; Osclass
confirmó “Selected languages have been enabled for the website”. Luego, en
*Settings > General*, se seleccionó `es_ES` como idioma predeterminado **sólo
del sitio público**. El controlador actualiza la preferencia `language`; la
sesión y preferencia del administrador usan un campo separado. Por eso la
administración permaneció en inglés.

Una sesión anónima nueva ahora renderiza `es-ES` y textos como `Buscar`,
`Iniciar sesión` y `Últimos anuncios`. El selector público conserva “English
(US)” y “Spanish (Spain)”; al seleccionar inglés en una sesión nueva se
renderiza `en-US`.

Esto también revela que la URL nativa de cambio acepta el locale instalado aun
cuando no esté listado: no debe interpretarse ese acceso directo como
habilitación pública ni como sustituto de una decisión de idioma. La interfaz
de administración permaneció utilizable en inglés tras la carga, la
habilitación y el cambio de predeterminado público.

Antes de tráfico real, verificar las plantillas de correo, los textos específicos
del tema y las categorías/contenido, que no forman parte de la traducción
automática del paquete. El idioma predeterminado de la administración permanece
en inglés y cualquier cambio allí requiere una decisión separada.

Fuentes primarias:

- <https://osclass-classifieds.com/translations>
- <https://osclass-classifieds.com/terms>
- <https://osclass-classifieds.com/license>

## Próximo paso

Definir el primer recorte funcional del marketplace y, antes de tráfico real o
datos personales, migrar el origen a Nginx/PHP-FPM, configurar correo/cron y
revisar copias de seguridad con los workspaces `servidor` y `cloudflare`.
