# Osclass detrás de Nginx — procedimiento

Estado: **aplicado y verificado** (2026-10-02). Nginx sirve `127.0.0.1:8783` y PHP corre en `127.0.0.1:8784`; por HTTPS, las páginas públicas responden `200`, `oc-admin` redirige a su login HTTPS, los archivos internos responden `404` y están presentes los encabezados de seguridad.

## Objetivo

Poner el Nginx ya instalado en la máquina delante del servidor de PHP de Osclass,
sin instalar paquetes ni cambiar Cloudflare. Nginx bloquea archivos internos que
hoy se leen desde Internet y agrega encabezados de seguridad; PHP sigue
ejecutando Osclass como hasta ahora.

| Antes | Después |
| --- | --- |
| Cloudflare Tunnel → PHP (`127.0.0.1:8783`) | Cloudflare Tunnel → Nginx (`127.0.0.1:8783`) → PHP (`127.0.0.1:8784`) |

El túnel sigue apuntando al puerto `8783`, por lo que Cloudflare no se modifica.

## Qué se bloquea

Verificado el 2026-10-01: estos archivos respondían `200` con su contenido.

| Archivo de ejemplo | Por qué se bloquea |
| --- | --- |
| `CHANGELOG.txt`, `.gitignore` | Revelan la versión exacta de Osclass. |
| `oc-includes/osclass/installer/struct.sql`, `mail.sql` | Muestran la estructura de la base. |
| `oc-content/plugins/*/README.md` | Documentación interna de los plugins. |
| `oc-includes/osclass/install.php`, `config-sample.php` | Instalador y configuración de ejemplo, innecesarios en un sitio instalado. |

`config.php` no se expone: PHP lo ejecuta y devuelve una respuesta vacía.

## Archivos involucrados

| Archivo versionado | Destino | Cambio |
| --- | --- | --- |
| `nginx/alquileres.conf` | `/etc/nginx/sites-available/alquileres` | Nuevo sitio de Nginx en `127.0.0.1:8783`. |
| `systemd/osclass.service` | `~/.config/systemd/user/osclass.service` | PHP pasa de `8783` a `8784`. Ya copiado y recargado; falta reiniciar el servicio. |

Nginx no sirve archivos por sí mismo: reenvía todo a PHP. Así no necesita
permisos de lectura sobre `/home/ignacio` (modo `750`), el problema que se
encontró con `gimnasia`.

## Prueba previa realizada

Se levantó una copia de Nginx sin privilegios en `127.0.0.1:18783`, con la misma
configuración y apuntando al PHP en funcionamiento:

- Los archivos internos listados arriba respondieron `404`.
- Portada, búsqueda con filtro, ficha `#3`, formulario de baja, hoja de estilos
  y `oc-admin` respondieron normalmente.
- `oc-admin` redirigió a `https://alquileres.diazignacio.ar/oc-admin/index.php?page=login`.

La copia de prueba se detuvo después de la verificación.

## Pasos

Ejecutar en una terminal de la máquina, en este orden. Los pasos 1 a 3 no
afectan al sitio; entre los pasos 4 y 5 el sitio queda sin servicio unos
segundos, por lo que conviene ejecutarlos uno inmediatamente después del otro.

**1. Ir al workspace**

```bash
cd /home/ignacio/fewohbee
```

**2. Instalar y activar el sitio de Nginx**

```bash
sudo install -m 644 nginx/alquileres.conf /etc/nginx/sites-available/alquileres
sudo ln -s /etc/nginx/sites-available/alquileres /etc/nginx/sites-enabled/alquileres
```

**3. Validar la configuración completa de Nginx**

```bash
sudo nginx -t
```

Debe terminar con `syntax is ok` y `test is successful`. Si falla, **no seguir**:
ejecutar la sección *Vuelta atrás* desde el paso A1 y avisar el error.

**4. Mover PHP al puerto 8784** (desde acá el sitio queda sin servicio)

```bash
systemctl --user restart osclass.service
```

**5. Recargar Nginx para que tome el puerto 8783** (el sitio vuelve)

```bash
sudo systemctl reload nginx
```

## Verificación

**Puertos:** Nginx en `8783` y PHP en `8784`.

```bash
ss -ltnp | grep -E ':878[34]\b'
```

**Sitio público:** todas deben responder `200`, salvo `oc-admin`, que responde `302`.

```bash
for u in / "/index.php?page=search&sCategory=47&tourist_type=Caba%C3%B1a" "/index.php?page=item&id=3" /directorio/solicitar-baja/3 /oc-admin/; do
  printf '%-62s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code}' "https://alquileres.diazignacio.ar$u")"
done
```

**Archivos internos:** todas deben responder `404`.

```bash
for u in /CHANGELOG.txt /.gitignore /oc-includes/osclass/installer/struct.sql /oc-content/languages/es_ES/mail.sql /oc-content/plugins/tourist-directory/README.md /oc-includes/osclass/install.php /config-sample.php; do
  printf '%-52s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code}' "https://alquileres.diazignacio.ar$u")"
done
```

**Encabezado de Nginx:**

```bash
curl -sI https://alquileres.diazignacio.ar/ | grep -i x-content-type-options
```

Debe mostrar `x-content-type-options: nosniff`.

## Vuelta atrás

Si algo falla después del paso 4, restaurar el esquema anterior:

**A1. Desactivar el sitio de Nginx y liberar el puerto 8783**

```bash
sudo rm -f /etc/nginx/sites-enabled/alquileres
sudo systemctl reload nginx
```

**A2. Devolver PHP al puerto 8783**

```bash
sed -i 's/127\.0\.0\.1:8784/127.0.0.1:8783/' ~/.config/systemd/user/osclass.service
systemctl --user daemon-reload
systemctl --user restart osclass.service
```

**A3. Comprobar que el sitio responde**

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://alquileres.diazignacio.ar/
```

Si la falla ocurrió en el paso 3, alcanza con el primer comando de A1
(`sudo rm -f ...`): PHP todavía no se movió y el sitio sigue funcionando.

## Pendiente a futuro (opción B)

Reemplazar el servidor de PHP por PHP-FPM (`php8.5-fpm`, hoy no instalado), con
Nginx comunicándose directamente con él. Es la configuración estándar para
producción y requiere autorizar la instalación de ese paquete y coordinar con
`../servidor/`. Conviene hacerlo antes de tener tráfico real sostenido.
