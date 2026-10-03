# Copias de seguridad automáticas de Osclass

Estado: **activo desde el 2026-10-02** (fase 1, sólo local).

## Qué se copia

Cada corrida de `scripts/osclass-backup.sh` genera un conjunto con marca de
tiempo en `data/osclass/backups/auto/` (ignorado por Git, modo `0700`):

| Archivo | Contenido |
|---------|-----------|
| `osclass-<AAAAMMDDTHHMMSS>.sql.zst` | Volcado de `osclass_local` (`mysqldump --single-transaction --no-tablespaces --routines --triggers`, utf8mb4). |
| `osclass-<…>.files.tar.zst` | `app/osclass/config.php` y `app/osclass/oc-content/uploads/`. |
| `osclass-<…>.sha256` | Sumas de verificación de los dos archivos anteriores. |

El script falla (código distinto de 0, sin dejar archivos parciales) si el
volcado no termina con `-- Dump completed` o si algún archivo no supera
`zstd -t`. Conserva los 14 conjuntos más recientes.

El código de Osclass, los plugins y el idioma no se copian: se reconstruyen
desde la versión oficial 8.3.1 y desde este repositorio (rama `osclass`).
Después de reinstalar `es_ES` hay que volver a correr
`data/osclass/fix_es_ES_placeholders.py`.

## Componentes

- `scripts/osclass-backup.sh`: script versionado, sin secretos. Variables
  opcionales: `OSCLASS_BACKUP_DIR`, `OSCLASS_BACKUP_KEEP`, `OSCLASS_BACKUP_CNF`,
  `OSCLASS_DB`.
- `data/osclass/backup.my.cnf` (`0600`, no versionado): archivo de opciones de
  MySQL con el usuario limitado `osclass_local`, generado a partir de
  `data/osclass/credentials.env`. Así la contraseña no aparece en la lista de
  procesos.
- `systemd/osclass-backup.service` y `systemd/osclass-backup.timer`: copiados a
  `~/.config/systemd/user/`. Corre todos los días a las 03:30 (±15 min);
  `Persistent=true` lo ejecuta al arrancar si el equipo estaba apagado. El
  usuario tiene *linger* habilitado.

## Operación

```sh
systemctl --user list-timers osclass-backup.timer   # próxima corrida
systemctl --user start osclass-backup.service       # copia manual inmediata
journalctl --user -u osclass-backup.service -n 20   # resultado
cd ~/fewohbee/data/osclass/backups/auto && sha256sum -c osclass-<…>.sha256
```

Al modificar las unidades versionadas, volver a copiarlas y ejecutar
`systemctl --user daemon-reload`.

## Restauración

Requiere autorización explícita: reemplaza el estado del sitio publicado.

1. Hacer una copia manual del estado actual (`systemctl --user start
   osclass-backup.service`) y verificar las sumas del conjunto a restaurar.
2. Detener el sitio: `systemctl --user stop osclass.service`.
3. Restaurar la base:
   `zstd -dc osclass-<…>.sql.zst | mysql --defaults-extra-file=$HOME/fewohbee/data/osclass/backup.my.cnf osclass_local`
4. Restaurar los archivos, si corresponde:
   `zstd -dc osclass-<…>.files.tar.zst | tar -C $HOME/fewohbee/app/osclass -xf -`
5. Iniciar el sitio (`systemctl --user start osclass.service`) y verificar la
   portada, una ficha y `oc-admin` por HTTPS.

## Verificación inicial (2026-10-02)

- Prueba en un directorio aislado: conjunto creado, sumas `OK` y rotación con
  `KEEP=2` correcta. Con credenciales inválidas o una base inexistente, el
  script terminó con código 2 sin dejar archivos.
- Prueba de restauración en una instancia MySQL 8.4 temporal y aislada (sin red,
  con socket propio, eliminada después): 41 tablas, 25 anuncios, 151 categorías
  y `pageTitle = Alquileres Temporarios`, iguales a la base en vivo.
- Primera corrida real con systemd: `osclass-20261002T222153` (~72 KB).

## Pendiente: fase 2

Las copias están en el mismo disco que la base (el servidor tiene un único
disco, `sda`). Protegen ante errores lógicos, pero no ante una falla del disco
o del equipo. Falta una copia fuera del host (candidato: el VPS, en coordinación
con `../vps/` y `../servidor/`).
