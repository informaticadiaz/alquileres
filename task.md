# FewohBee — plan de trabajo

> **Documento histórico.** FewohBee fue retirado el 2026-09-27; este archivo
> no describe el estado vigente. Ver `CODEX_STATE.md`.

## Estado actual

**Demo publicada** en https://alquileres.diazignacio.ar (Fase P). Fase 4 con pendientes bloqueados externamente. El objetivo es
validar el producto con una variante liviana basada en SQLite, sin convertirla
todavía en una instalación operativa ni exponerla públicamente.

## Objetivo

Construir un fork experimental de FewohBee para una prueba de mercado en una
sola máquina y con bajo volumen de escritura, usando SQLite como backend y
manteniendo una ruta de salida hacia el FewohBee upstream con MySQL/MariaDB.

## Decisiones tomadas

- SQLite es aceptable para la prueba inicial; no se busca compatibilidad
  transparente con el backend upstream.
- El fork vive en la rama `sqlite-market-validation` del repositorio clonado en
  `app/`.
- El baseline SQLite tendrá su propia línea de inicialización y migraciones; no
  ejecutará la historia de migraciones MySQL.
- El perfil SQLite es experimental, de una sola máquina y no contempla todavía
  multiinstancia, alta disponibilidad ni acceso al archivo por red.
- MariaDB/MySQL permanece como baseline upstream y ruta de migración, no como
  decisión predeterminada.
- Los seeds locales actuales son datos técnicos de prueba, no textos aptos para
  huéspedes, comunicaciones legales o producción.
- 2026-09-27: los templates locales quedan **sólo como fixtures técnicos**. El
  conjunto operativo con procedencia y licencia se posterga hasta antes del
  piloto (Fase 5); la prioridad es validar concurrencia e integridad.

## Tareas completadas

### Preparación y evaluación

- [x] Crear el workspace `/home/ignacio/fewohbee` con `AGENTS.md`,
      `CODEX_STATE.md` y `data/`.
- [x] Clonar la aplicación upstream en `app/`.
- [x] Clonar el despliegue Compose oficial en
      `data/fewohbee-dockerized/`.
- [x] Verificar que el host no tenía Docker/Compose, PHP ni Composer.
- [x] Instalar el toolchain de desarrollo: PHP 8.5.4, Composer 2.9.5 y las
      extensiones necesarias, incluida `pdo_sqlite`.
- [x] Diseñar el port en `port-sqlite-design.md`.
- [x] Congelar el baseline upstream en
      `f33b33565939373a16074e0a8fcc5277c75ec0c8`.
- [x] Inventariar los acoplamientos MySQL y crear
      `sqlite-port-manifest.md`.

### Fase 1 — infraestructura de pruebas SQLite

- [x] Crear el perfil `sqlite_test` sin modificar el perfil MySQL.
- [x] Configurar dos conexiones SQLite (`default` y `geo`) sobre un fixture
      efímero ignorado.
- [x] Activar y verificar `foreign_keys`, WAL y `busy_timeout` de 5000 ms por
      conexión.
- [x] Agregar prueba TDD del perfil: **1 prueba, 10 aserciones**.

### Fase 2 — baseline y primer arranque técnico

- [x] Crear `SqliteBaselineInitializer` para una base SQLite fresca.
- [x] Crear el esquema desde los mappings ORM actuales, sin ejecutar
      migraciones MySQL.
- [x] Registrar el baseline `sqlite-schema-tool-v1`.
- [x] Verificar tablas, geodatos, claves foráneas, WAL y ausencia de
      `doctrine_migration_versions`: **2 pruebas, 26 aserciones**.
- [x] Sustituir, sólo en `sqlite_test`, la descarga remota de templates por
      siete seeds locales técnicos.
- [x] Verificar que `app:first-run` funciona sin red, es repetible y respeta
      `--if-not-initialized`: **1 prueba, 7 aserciones**.

## Próximas tareas

### Fase 2 — cerrar el baseline operativo

- [x] Decidir el alcance de los templates locales: sólo fixtures (2026-09-27).
- [ ] Aislar todos los seeds de `app:first-run` que aún dependan de red o de
      datos no versionados.
- [ ] Definir una fuente local versionada para esos seeds y su política de
      actualización.
- [ ] Completar la prueba de primer arranque con el conjunto local aprobado.
- [ ] Documentar qué datos son técnicos, cuáles son configurables y cuáles no
      deben entrar en el fork.

### Fase 3 — comportamiento de dominio

- [x] Adaptar `ImportPostalcodedataCommand` sin `SET FOREIGN_KEY_CHECKS`: el
      toggle sólo corre en `AbstractMySQLPlatform`; `--override` en SQLite
      verificado con FK activas y `foreign_key_check` vacío
      (`SqlitePostalcodeImportTest`: **1 prueba, 9 aserciones**).
- [x] Mapear límites transaccionales (2026-09-27). Hallazgos: ningún camino
      usa bloqueos; la única transacción explícita es
      `BankStatementCommitter::commit`. Riesgos ordenados: (1) sobreventa en
      `PublicBookingService::createBooking` (chequeo→escritura sin lock);
      (2) numeración de facturas y asientos por `max+1` sin índice único, y
      `createEntriesFromInvoice` con dos flushes sin transacción; (3)
      workflows programados (acción antes del log, sin lock de proceso) e
      iCal (`refUid` sin índice único). Estrategia: envolver
      chequeo+escritura en `BEGIN IMMEDIATE`, que en SQLite serializa a los
      escritores.
- [x] Impedir la sobreventa en la reserva online (2026-09-27). RED
      reproducido: dos escritores concurrentes reservaban la misma habitación.
      GREEN: `SqliteImmediateTransactionConnection` hace que toda transacción
      DBAL en SQLite arranque con `BEGIN IMMEDIATE`, y
      `PublicBookingService::createBooking` vuelve a chequear y persiste dentro
      de una transacción (sin cliente huérfano si falla).
      `SqlitePublicBookingConcurrencyTest`: **2 pruebas**; suite SQLite
      **6 pruebas, 63 aserciones**. Un timeout del lock se convierte en
      `online_booking.error.booking_busy` (de/en), que el formulario público
      muestra sin error de base de datos. Pendiente: correr
      `PublicBookingControllerTest` con MySQL cuando haya entorno.
- [x] Evitar lotes mensuales duplicados en el diario (2026-09-27). RED: dos
      escritores en un mes nuevo creaban dos `BookingBatch` para el mismo mes.
      GREEN: `BookingJournalService::createEntriesFromInvoice` hace
      búsqueda/alta del lote, asientos y renumeración en una transacción
      (`SqliteBookingJournalConcurrencyTest`; suite SQLite **7 pruebas, 77
      aserciones**; 118 unit tests del diario OK). Análisis: los asientos de
      una factura ya salían en un solo flush y la renumeración anual corrige
      los "último + 1" concurrentes.
- [x] **Riesgo aceptado** (2026-09-27) — Numeración de facturas: el chequeo de duplicado y el guardado en
      `InvoiceServiceController::createNewInvoiceAction` no son atómicos y
      `invoices.number` no tiene índice único. Riesgo acotado a dos
      administradores guardando en el mismo instante. Se acepta para la prueba de
      mercado; revisar antes de un piloto con varios administradores.
- [x] Comparación de fechas en SQLite (2026-09-27, estrategia A). Los tipos
      `SqliteDateType`/`SqliteDateImmutableType` (sólo en `sqlite_test`)
      guardan `Y-m-d 00:00:00`, así las columnas `date` comparan bien contra
      parámetros `DateTime`. `RoomBlockRepository`, `TouristTaxRepository` y
      `GuestCategoryModifierRepository` pasan parámetros tipados como fecha
      (en MySQL renderizan igual). `SqliteDateComparisonTest`: **2 pruebas**.
      Limitación conocida: `RegistrationBookEntryRepository` (módulo marcado
      para eliminarse upstream) sigue comparando con texto `Y-m-d`.
- [x] Solapamiento de `workflow:process-scheduled` (2026-09-27): lock de
      proceso no bloqueante con `flock()` en
      `var/lock/workflow-process-scheduled.lock`; `--dry-run` no lo toma.
      `SqliteScheduledWorkflowsOverlapTest`. Suite SQLite **10 pruebas, 106
      aserciones**; unit tests **1344 OK**. Aceptado: un crash entre la
      acción y el log puede repetir la acción (entrega al-menos-una-vez).
- [x] iCal (2026-09-27): RED reproducido, dos sincronizaciones concurrentes
      importaban dos veces el mismo evento. GREEN:
      `ImportedReservationSynchronizer::synchronize` busca por UID, chequea
      conflictos y guarda en una transacción; `CalendarImportBookingCreatedEvent`
      se despacha después del commit. `SqliteCalendarImportConcurrencyTest`.
      Suite SQLite **11 pruebas, 118 aserciones**; unit **1344 OK**.
      Pendiente menor: un timeout del lock corta el resto de esa corrida de
      `syncImport` sin registrar `lastSyncError`; la corrida siguiente
      reintenta.
- [x] Transacciones breves: reserva online, diario contable e importación
      iCal (ver arriba). Factura: riesgo aceptado.
- [x] Reserva manual: el panel permite sobreventa por diseño (sólo avisa);
      reserva online concurrente cubierta.
- [x] iCal concurrente e idempotente por UID (ver arriba).
- [x] Workflows programados serializados con lock de proceso.
- [x] `database is locked`: cubierto en cada prueba de concurrencia; el
      escritor bloqueado espera `busy_timeout` y falla con un error claro. Sin
      reintentos automáticos por decisión: el usuario o la corrida siguiente
      reintentan.

**Fase 3 cerrada el 2026-09-27.**

### Fase 4 — evolución y operación recuperable

- [x] Línea de migraciones SQLite (2026-09-27): `migrations-sqlite/`
      (namespace `SqliteMigrations`, tabla
      `fewohbee_sqlite_migration_versions`). `SqliteMigrationLinePass` quita
      el path MySQL que Symfony fusiona en `sqlite_test`. El baseline marca
      toda la línea como aplicada; `Version20260927000000` aborta sobre bases
      no creadas por el baseline. Test de deriva: base nueva == mappings.
      `SqliteMigrationsTest`; suite SQLite **15 pruebas, 149 aserciones**.
      Nota: SQLite reserva el prefijo `sqlite_` para tablas internas.
- [ ] Procedimiento por cada merge upstream con migraciones nuevas: (1) por
      cada `migrations/VersionX.php` escribir `migrations-sqlite/VersionX.php`
      equivalente (mismo timestamp, docblock que cite la migración MySQL);
      (2) probar sobre una copia de backup con
      `doctrine:migrations:migrate --env=sqlite_test`; (3) exigir que
      `SqliteMigrationsTest` (deriva cero) siga verde. Pendiente: primer
      caso real para validarlo.
- [ ] Registrar la correspondencia entre cada cambio upstream y su migración
      SQLite equivalente.
- [x] Backup consistente (2026-09-27): `app:sqlite:backup <destino>` usa
      `VACUUM INTO` (una transacción de lectura: incluye lo confirmado aún en
      el WAL, excluye escrituras sin confirmar, no requiere detener la app).
      No sobrescribe destinos existentes y borra la copia si no pasa las
      verificaciones. `SqliteBackupTest`; suite SQLite **12 pruebas, 136
      aserciones**.
- [x] `integrity_check` y `foreign_key_check` automáticos sobre cada copia;
      restauración probada: con la app detenida, borrar base/`-wal`/`-shm`,
      copiar el backup y arrancar (vuelve a WAL y pasa `integrity_check`).
- [ ] Programar backups periódicos, retención y copia fuera del host:
      requiere coordinar con `../servidor/` y autorización explícita.
- [ ] Crear exportación de salida hacia MariaDB con una copia de prueba.

### Fase 5 — piloto acotado

- [ ] Ejecutar sólo con datos ficticios y una instancia local.
- [ ] Medir latencia de escritura, bloqueos, errores y duración de backups.
- [ ] Verificar actualización y restauración antes de cargar datos reales.
- [ ] Definir criterios de continuidad SQLite o migración a MariaDB.

### Fase P — publicación en `alquileres.diazignacio.ar`

Declarada el 2026-09-27. Objetivo: servir el fork SQLite como **servicio de
usuario** (`systemd --user`, sin root) detrás del túnel Cloudflare existente,
sin afectar los servicios publicados. Relevamiento de solo lectura de
`cloudflare/`, `servidor/`, `gimnasia/` y `construccion/` hecho el mismo día.

Contexto verificado:

- Túnel `pc-ignacio-ssh`: `cloudflared` corre como servicio root con
  configuración remota (Dashboard). Agregar un hostname no requiere sudo ni
  reiniciar `cloudflared`; se hace a mano en Zero Trust → Tunnels → Public
  Hostname (procedimiento de `cloudflare/20-construccion.md`).
- Modelo a copiar: `construccion` (unidad de usuario, `127.0.0.1:8782`,
  `EnvironmentFile=%h/.config/<app>/*.env` modo 600, `NoNewPrivileges`,
  `UMask=0077`, Cloudflare directo al puerto, sin nginx).
- Linger activo para `ignacio`; logs con `journalctl --user -u <unidad>`.
- Puerto propuesto: `127.0.0.1:8783` (libre al 2026-09-27).
- PHP disponible: sólo CLI 8.5.4 (sin `php-fpm`, sin nginx propio). Sin sudo,
  la única opción es `php -S`, que PHP documenta como no apto para producción.

Tareas (cada una indica quién la ejecuta y qué autorización requiere):

- [x] P1. **Runtime PHP: `php -S`** (decidido por el usuario el 2026-09-27; revisar FrankenPHP si la demo crece). Opciones evaluadas: `php -S` en unidad de
      usuario (sin instalar nada; un proceso, sólo aceptable para demo con
      pocos usuarios) / `php8.5-fpm` como servicio de usuario + nginx existente
      (requiere sudo, sitio nginx y copia en `/srv`, como `gimnasia`) /
      FrankenPHP como binario de usuario (descarga; sin precedente local).
- [x] P2. Perfil operativo `sqlite_prod` (2026-09-27, `c5c142f`): config SQLite
      compartida en `config/sqlite/`; base en `FEWOHBEE_SQLITE_PATH`;
      hereda `when@prod` de upstream vía alias YAML (`when@sqlite_prod:
      *when_prod` en 5 archivos); sesiones en `var/sessions/sqlite_prod` con
      GC; `APP_SECRET` obligatorio desde el entorno; `app:sqlite:init` crea el
      baseline (idempotente). `SqliteProdProfileTest`; suite SQLite **16
      pruebas, 165 aserciones**. Entornos MySQL `test`/`prod` verificados sin
      cambios.
- [x] P3. Seguridad antes de exponer (2026-09-27): `sqlite_prod` agregado a
      `extra.runtime.prod_envs` (sin eso `symfony/runtime` lo correría en
      debug); `login_throttling` 5 intentos / 15 min en `main`; cabeceras
      `nosniff`, `Referrer-Policy`, `X-Frame-Options: DENY` (salvo rutas con
      `frame-ancestors`); `TRUSTED_PROXIES=127.0.0.1` (va en el `.env` del
      servicio) verificado: IP del visitante y HTTPS sólo vía cloudflared;
      errores sin detalles internos. `SqliteProdSecurityTest` (4 pruebas).
      Rutas públicas: se mantienen las de upstream; la reserva online queda
      deshabilitada por defecto (configuración) durante la demo. Cookie de
      sesión `Secure` vía `cookie_secure: auto` + proxy confiable.
      Nota: tras cambiar `composer.json` hay que regenerar el autoload
      (`composer install`/`dump-autoload`) para que `prod_envs` aplique.
- [x] P4. Servicio de usuario en marcha (2026-09-27), autorizado por el
      usuario:
      - Checkout de despliegue: `git worktree` detached en
        `~/.local/share/fewohbee/app` (commit `103c25f`), `composer install
        --no-dev --no-scripts --classmap-authoritative`, `cache:warmup`,
        `importmap:install` (81 paquetes JS del CDN) y `asset-map:compile`.
      - Entorno: `~/.config/fewohbee/fewohbee.env` (600; `APP_ENV`,
        `APP_SECRET` aleatorio, `FEWOHBEE_SQLITE_PATH`, `TRUSTED_PROXIES`,
        `DEFAULT_URI`, correo deshabilitado con `MAILER_DSN=null://null`).
      - Base: `~/.local/share/fewohbee/data/fewohbee.sqlite` (dir 700, archivo
        600), `app:sqlite:init` + `app:first-run` con usuario `admin` (sin
        datos de ejemplo). Contraseña entregada sólo por chat.
      - Unidad versionada `fewohbee/systemd/fewohbee.service`, instalada en
        `~/.config/systemd/user/`, habilitada: `php -S 127.0.0.1:8783 -t
        public`, `PHP_CLI_SERVER_WORKERS=4`, `variables_order=EGPCS`,
        `NoNewPrivileges`, `UMask=0077`.
      - Verificado en local como cloudflared: `/login` 200 con cabeceras de
        seguridad, 404 sin detalles internos, estáticos 200,
        `/health/live` y `/health/ready` 200, login admin → dashboard 200;
        escucha sólo en `127.0.0.1:8783`; construccion, reparto y blog siguen
        activos.
      - Bug encontrado y corregido durante el despliegue (`103c25f`): el
        `schema_filter` ocultaba la tabla de versiones de migraciones.
      - Actualizar: `git -C ~/.local/share/fewohbee/app checkout --detach
        <commit>` + `composer install --no-dev ...` + `cache:clear` +
        `asset-map:compile` + `doctrine:migrations:migrate` +
        `systemctl --user restart fewohbee`. Backup antes de migrar.
      - Operar: `systemctl --user status|restart fewohbee`,
        `journalctl --user -u fewohbee`.
- [x] P5. Backups programados (2026-09-27, `60e6296`): `app:sqlite:backup
      --dir <dir> --keep N` crea `fewohbee-<UTC Ymd-His-u>.sqlite` (600) y
      recién tras verificarla borra las más viejas por encima de N; nunca
      toca otros archivos. Timer de usuario `fewohbee-backup.timer` (diario
      03:30 ± 15 min, `Persistent=true` para correr al volver de apagado o
      suspensión) → `fewohbee-backup.service` con `--keep=14` en
      `~/.local/share/fewohbee/backups` (700). Unidades versionadas en
      `fewohbee/systemd/`. Primera corrida manual OK. Los backups manuales
      `pre-update-*.sqlite` no se rotan: borrarlos a mano.
- [ ] P5b. **Copia fuera del host**: bloqueada. La política corresponde a
      `../servidor/syncthing/`, cuya carpeta compartida aún no está operativa.
      Decisión del usuario: destino (Syncthing a otro dispositivo, otro
      host, nube) y frecuencia.
- [x] P6. Registro en `cloudflare/` (2026-09-27): runbook
      `../cloudflare/21-alquileres.md`, entrada `fewohbee-alquileres` en
      `services-manifest.json` (`draft`, puerto 8783 en `port_allowlist`,
      health `/health/ready`, `application_auth_required`), `AGENTS.md` y
      `CODEX_STATE.md` de `cloudflare/` actualizados. Preview sin sudo OK:
      origen local responde; bloqueos esperados (`draft`, backend remoto).
      Verificado en local: `/` y rutas protegidas redirigen a
      `https://alquileres.diazignacio.ar/login`.
- [x] P7. Publicado (2026-09-27): el usuario agregó el Public Hostname
      `alquileres.diazignacio.ar` → HTTP `127.0.0.1:8783` desde el Dashboard.
- [x] P8. Verificación pública desde el servidor (2026-09-27): DNS en
      1.1.1.1/8.8.8.8 → IPs de Cloudflare; `/health/ready` 200; `/` y rutas
      protegidas → 302 a `https://alquileres.diazignacio.ar/login`;
      cabeceras de seguridad presentes; 404 sin detalles; cookies `secure;
      httponly; samesite=lax`. Manifiesto en `enabled`; estado actualizado en
      `fewohbee/`, `cloudflare/` y raíz. Retiro documentado en
      `../cloudflare/21-alquileres.md`. `servidor/` no tiene aún inventario de
      puertos (pendiente de su plan de seguridad); el puerto quedó en el
      `port_allowlist` del manifiesto.
- [ ] P8b. Confirmación del usuario desde el celular con datos móviles.
- [ ] P8c. Cambiar la contraseña del usuario `admin` de demo desde la app.

Restricciones: sólo datos ficticios hasta cerrar la Fase 5; no usar el modo
Compose upstream (80/443); no abrir permisos de `/home/ignacio`; no correr
`cloudflared tunnel route dns` desde la terminal.

### Publicación del código

- [x] 2026-09-27: el fork de la app se publicó en
      https://github.com/informaticadiaz/alquileres (público, GPL-3.0), rama
      `main` = `sqlite-market-validation` (historial de upstream + commits del
      fork). Remoto local `alquileres` en `app/`; `origin` sigue apuntando a
      upstream. Incluye `README.sqlite.md` y unidades de ejemplo sin datos
      reales en `deploy/sqlite/`. Decisión del usuario: la documentación
      operativa del workspace y el runbook de Cloudflare **no** se publican
      (describen la infraestructura del servidor).
- Para publicar nuevos commits: `git -C app push alquileres
  sqlite-market-validation:main`, revisando antes que el diff no incluya
  secretos ni datos del servidor.

### Fase T — traducción al español

Declarada el 2026-09-27 a pedido del usuario. FewohBee upstream sólo incluye
alemán e inglés (`config/packages/translation.yaml`: `enabled_locales: ['de',
'en']`). La demo usa `LOCALE=en` desde el 2026-09-27 (entorno del servicio).

Decisiones del usuario:

- Traducir **del inglés al español**.
- La traducción debe quedar **disponible en el repositorio del fork**
  (https://github.com/informaticadiaz/alquileres).

Alcance estimado: ~1.500 cadenas en ~36 archivos de `translations/` (dominios
por área de producto) más contenido fuera de `translations/` (plantillas de
correo/PDF, seeds de first-run, textos de workflows del sistema).

Tareas:

- [x] T1. Inventario (2026-09-27): el inglés son 33 archivos, todos
      `*.en.yaml` (el alemán mezcla `.xlf` y `.yaml`). Catálogos `de` y `en`
      con paridad casi total (3.091 claves cada uno); única diferencia:
      `Family` (de) vs `family` (en) en `messages`. Claves propias de la app
      en inglés: **2.882, ~13.800 palabras** (`messages` 2.708,
      `subdivisions` 108, `Housekeeping` 34, `validators` 22, `security` 10).
      Vendor ya trae español: validadores y formularios de Symfony,
      `security-core` y `ResetPasswordBundle`. Formatos a preservar: `%var%`
      (~290), `{var}`, `{{ var }}`, 8 plurales de Symfony (`{1}...|]1,Inf]...`,
      sin ICU) y 18 textos con HTML. Nota para T5: los saludos por defecto de
      clientes (`AppSettings::$customerSalutations` = `Ms`, `Mr`, `Family`) se
      traducen por clave y el inglés sólo define `family` en minúscula.
- [x] T2. Prueba de paridad (2026-09-27):
      `tests/Unit/Translation/SpanishTranslationParityTest.php`. Por cada
      `*.en.yaml` exige su `*.es.yaml` con las mismas claves, placeholders,
      intervalos de plural y etiquetas HTML, y valores no vacíos. Sin `es`
      habilitado, un archivo sin traducir queda *incomplete*; con `es` en
      `enabled_locales` pasa a ser falla. Verificada con un archivo
      defectuoso (clave faltante, clave extra y placeholder inventado
      detectados). Primer archivo traducido: `Login/security.es.yaml`
      (1/33).
- [x] T3. Traducción inglés → español (2026-09-27): 33/33 archivos
      `*.es.yaml` (27 commits `feat(translation): ...`, sin empujar).
      Guía: **tuteo**, español neutro, sin voseo ni regionalismos (barrido:
      0 formas de usted, 0 de voseo), glosario fijo (reserva, huésped,
      habitación, establecimiento, factura, asiento, libro de caja/diario,
      tasa turística, flujo de trabajo...). Paridad: 34 pruebas, 11.636
      aserciones, 0 incompletas; `lint:yaml` OK en 82 archivos; unit suite OK.
      Observaciones sobre el inglés de upstream: `TEMPLATE_CASHJOURNAL_PDF`
      dice "PDF Booking Journal" (se tradujo "Libro diario en PDF");
      `reservation.no.selected.appartments` dice "No reservation selected";
      claves con typos (`copy.sucess`, `reservation.custumer.selected`)
      conservadas. Términos alemanes sin equivalente conservados (Leitweg-ID,
      SKR03/04, §13b, GiroCode, clave BU de DATEV).
- [x] T4. `es` habilitado (2026-09-27): `enabled_locales: ['de', 'en',
      'es']`; fallbacks `['en', 'de']` (español → inglés → alemán; inglés
      sigue cayendo en alemán; alemán ahora cae en inglés en vez de mostrar
      la clave). Paridad ahora estricta. `SpanishLocaleTest` (login en
      español con `LOCALE=es`, fallback a inglés). `LOCALE=es` documentado
      en `deploy/sqlite/fewohbee.env.example` y `README.sqlite.md`.
- [x] T5. Contenido fuera de `translations/` (2026-09-27):
      - Bug corregido (`b7417e9`): el saludo por defecto `Family` no existía
        en inglés, así que caía en alemán y los clientes se guardaban como
        "Familie" (también en la UI en inglés). Agregado `Family` en `en`/`es`
        y test de los tres saludos.
      - Datos sembrados por `app:first-run` (categorías de huésped siempre;
        con datos de ejemplo también categorías, orígenes, estados, precios)
        se traducen **al momento de sembrar** con `LOCALE` y no cambian
        después; test que lo fija. **La base de la demo se sembró en alemán**
        (antes de fijar `LOCALE`): resolver en T7.
      - No se traduce: cliente anónimo "Anonym" (identificador funcional usado
        por `CustomerService` al anonimizar; saludo "Herr" cosmético),
        estado de sistema `canceled_noshow` (se muestra por clave), vistas
        previas de plantillas con valores alemanes. Plantillas de correo/PDF
        son contenido del usuario. Documentado en `README.sqlite.md`.
- [x] T6. Verificación (2026-09-27, `769228c`):
      `SpanishInterfaceWalkthroughTest` recorre con `LOCALE=es` y datos de
      ejemplo todas las pantallas GET sin parámetros (~80; 13 s) y falla ante
      texto de interfaz en inglés (valor del catálogo `en` cuyo `es` difiere)
      o en alemán; ninguna pantalla da 5xx. Encontró y se corrigió:
      `aria-label="Close"` escrito a mano en 20 botones; encabezado
      "Default" en la lista de plantillas; etiquetas derivadas del nombre del
      campo sin clave ("Name", "Email", agregadas en de/en/es); ejemplo
      `<code>Website</code>` en la ayuda de origen. Excepción: encabezado CSV
      de ejemplo de un banco alemán (dato, no interfaz).
      `debug:translation` no corre en este checkout (conflicto de
      `php-parser` entre `rector` y el proyecto).
- [ ] T6b. **Formato de fechas** (decisión pendiente): upstream escribe a mano
      `d.m.Y` en 106 lugares de 53 archivos (también en inglés) y varios
      formularios lo parsean. En español se entiende, pero lo habitual es
      `dd/mm/aaaa`. Cambiarlo implica refactorizar formateo y parseo.
- [x] T7. Publicada y desplegada (2026-09-27): la rama
      `sqlite-market-validation` se publicó como `main` del fork
      `informaticadiaz/alquileres` en `769228c`. La demo en
      `https://alquileres.diazignacio.ar` se actualizó a ese commit con
      `LOCALE=es`; luego de regenerar la caché `sqlite_prod`, el login público
      muestra «Iniciar sesión», «Nombre de usuario» y «Contraseña». Salud local
      y pública verificadas.
- [ ] T8. Mantenimiento: en cada merge de upstream, traducir las claves nuevas
      de `*.en.*`; la prueba de paridad de T2 debe detectarlas. Evaluar más
      adelante si proponer la traducción a upstream.

## Reglas para retomar el trabajo

1. Trabajar en `app/sqlite-market-validation`.
2. Mantener intacto el camino MySQL/MariaDB upstream.
3. Aplicar RED → GREEN → REFACTOR en cada unidad de código.
4. Ejecutar las pruebas enfocadas antes de ampliar el alcance.
5. No ejecutar migraciones MySQL contra SQLite.
6. No usar datos reales, no desplegar servicios y no publicar DNS/túneles sin
   autorización específica.
7. Si una prueba de integridad o concurrencia falla, detener el fork y revisar
   la decisión antes de continuar.

## Criterios de aceptación del port

- [ ] Una instalación SQLite nueva se inicializa sin red ni migraciones MySQL.
- [ ] El esquema y los seeds aprobados están versionados y son reproducibles.
- [ ] No existe sobreventa en las pruebas concurrentes de reserva.
- [ ] Facturas, pagos y asientos no quedan parcialmente persistidos.
- [ ] Los backups se restauran y pasan las comprobaciones de integridad.
- [ ] Cada actualización upstream con cambios de esquema tiene una migración
      SQLite documentada y probada.
- [ ] Existe una exportación verificada hacia MariaDB antes del piloto real.

## Evidencia y archivos de referencia

- `CODEX_STATE.md` — continuidad del workspace y estado operativo.
- `port-sqlite-design.md` — diseño técnico y límites del fork.
- `sqlite-port-manifest.md` — baseline, inventario y evidencia por fase.
- `app/` — código upstream y rama `sqlite-market-validation`.
- `data/fewohbee-dockerized/` — despliegue Compose upstream, no utilizado por
  el port SQLite actual.

## Cómo continuar

El próximo trabajo concreto es cerrar los seeds locales operativos y luego
comenzar la Fase 3 con pruebas de dominio. No se debe instalar, desplegar ni
exponer una instancia de producción como parte de esta pausa.

## Retiro local

- [x] 2026-09-27: el usuario decidió retirar FewohBee porque el objetivo real
      es una vidriera pública multipropietario. Se deshabilitaron y borraron el
      servicio y timer instalados, configuración, checkout de despliegue, base
      SQLite, backups y clones locales de código. Se conservó esta
      documentación; el código confirmado permanece en
      `https://github.com/informaticadiaz/alquileres` (`main` = `769228c`).
- [ ] El hostname público de Cloudflare queda fuera de este retiro y requiere
      coordinación explícita con `../cloudflare/`.
