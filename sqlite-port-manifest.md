# Manifiesto de port SQLite — Fases 0, 1 y 2 (primer corte)

**Estado:** Fases 0 y 1 completadas; Fase 2 tiene baseline y un primer
`app:first-run` SQLite sin red. No es todavía un baseline operativo completo.

## Identidad del baseline

| Campo | Valor verificado |
| --- | --- |
| Repositorio upstream | `https://github.com/developeregrem/fewohbee.git` |
| SHA congelado | `f33b33565939373a16074e0a8fcc5277c75ec0c8` |
| Rama local del fork | `sqlite-market-validation` |
| Punto de partida de la rama | El SHA congelado; `origin/master` resolvía al mismo SHA durante la verificación. |
| Estado del árbol al crear la rama | Limpio; no había cambios locales ni rama SQLite previa. |
| Alcance | Una sola máquina, bajo volumen de escritura, sin datos reales ni exposición pública. |
| Soporte upstream | MySQL/MariaDB. SQLite es experimental y propio del fork. |

## Inventario de compatibilidad verificado

| Área o archivo | Acoplamiento MySQL verificado | Disposición SQLite planificada | Estado de evidencia |
| --- | --- | --- | --- |
| `config/packages/doctrine.yaml` | Ambas conexiones, `default` y `geo`, usan `pdo_mysql`, versión servidor, `utf8mb4` y collation MySQL. | Crear un perfil SQLite aislado con dos conexiones `pdo_sqlite` al mismo archivo local y conservar `schema_filter`. No modificar el perfil MySQL. | Inspección estática: verificado. Prueba SQLite: pendiente. |
| `.env.dist` | URL base `mysql://` y `DB_SERVER_VERSION=8.0`. | Añadir ejemplo específico del fork para URL/ruta SQLite, sin secretos ni archivo de base versionado. | Inspección estática: verificado. Prueba de arranque: pendiente. |
| `config/packages/doctrine_migrations.yaml` | Una única ruta/tabla de migraciones para la historia upstream. | Mantener sin cambios en este corte; el baseline separado queda marcado por `fewohbee_sqlite_baseline_metadata`, no por esa historia. | Inspección estática: verificado. Namespace/versionado posterior: pendiente. |
| `migrations/Version20190802123327.php` | Guarda `AbstractMySQLPlatform`; DDL con InnoDB, `AUTO_INCREMENT`, collations, tipos MySQL y `ALTER TABLE` para FKs. | No ejecutar. Crear baseline SQLite nuevo desde entidades y seeds requeridos. | Verificado; traducción pendiente. |
| `migrations/Version20190831115321.php` | Guarda `AbstractMySQLPlatform`. | No ejecutar; absorber su resultado en baseline SQLite y registrar equivalencia. | Verificado; traducción pendiente. |
| `migrations/Version20200201112113.php` | Guarda `AbstractMySQLPlatform`; usa `AUTO_INCREMENT`, `CHANGE`, charset/collation. | No ejecutar; absorber en baseline SQLite. | Verificado; traducción pendiente. |
| `migrations/Version20200229105042.php` | Guarda `AbstractMySQLPlatform`; usa `CHANGE`, `DROP FOREIGN KEY`, InnoDB y collation. | No ejecutar; absorber en baseline SQLite. | Verificado; traducción pendiente. |
| Migraciones con SQL de dialecto detectado | La exploración detectó `AUTO_INCREMENT`, `TINYINT`, `ENGINE`, `CHANGE`, `DROP FOREIGN KEY` o `INSERT IGNORE` en la lista exacta siguiente. | Revisar una por una en cada integración upstream; crear equivalente SQLite sólo cuando el cambio posterior al baseline sea necesario. | Escaneo estático: verificado. Matriz de equivalencia: pendiente. |
| `src/Command/ImportPostalcodedataCommand.php` | Ejecuta `SET FOREIGN_KEY_CHECKS=0/1` al vaciar `postal_code_data`. | Extraer estrategia por plataforma; SQLite mantendrá FKs activas y usará una operación compatible, cubierta por prueba de importación. | Inspección estática: verificado. Prueba: pendiente. |
| `tests/bootstrap.php` | Sólo borra `var/test.db` si la URL coincide con una cadena SQLite concreta. | Generalizar fixture aislado y limpieza segura para SQLite, sin tocar base operativa. | Inspección estática: verificado. Prueba: pendiente. |
| `bin/run-tests.sh` | Recrea base y aplica la historia de migraciones actual. | Añadir carril SQLite que inicialice baseline SQLite y conserve el carril MySQL sin regresión. | Inspección estática: verificado. Prueba: pendiente. |
| `app/README.md` | Declara MySQL 8+ o MariaDB como requisito. | Documentar SQLite sólo en la documentación del fork, con límites y migración de salida. | Inspección estática: verificado. Revisión documental: pendiente. |

### Lista exacta de migraciones con patrones MySQL detectados

`Version20190802123327.php`, `Version20200201112113.php`, `Version20200229105042.php`, `Version20210117110810.php`, `Version20210207103847.php`, `Version20210227203949.php`, `Version20211008204004.php`, `Version20211016103915.php`, `Version20211204122332.php`, `Version20220212121500.php`, `Version20220820130837.php`, `Version20220923121903.php`, `Version20250206125900.php`, `Version20250311083124.php`, `Version20250407071506.php`, `Version20251121120640.php`, `Version20260104090000.php`, `Version20260115120000.php`, `Version20260121120000.php`, `Version20260223090000.php`, `Version20260320120000.php`, `Version20260321120000.php`, `Version20260402073403.php`, `Version20260413120000.php`, `Version20260420120000.php`, `Version20260421120000.php`, `Version20260422130000.php`, `Version20260424190000.php`, `Version20260505120000.php`, `Version20260513120000.php`, `Version20260523194549.php`, `Version20260526115836.php`, `Version20260531120000.php`, `Version20260707120000.php`, `Version20260711120000.php`, `Version20260712110000.php`, `Version20260712120000.php`, `Version20260728120000.php`, `Version20260813090000.php`, `Version20260817120000.php`, `Version20260823120000.php`, `Version20260907153507.php`, `Version20260915120000.php`, `Version20260917120000.php`.

Esta lista es un inventario de revisión, no una afirmación de que cada línea de cada archivo sea incompatible. Ninguna de estas migraciones se ejecutará contra SQLite.

## Matriz de compatibilidad

| Capacidad | MySQL/MariaDB upstream | SQLite del fork | Decisión |
| --- | --- | --- | --- |
| Instalación nueva | Soportada por upstream. | Corte técnico de baseline desde metadatos Doctrine, sólo en `sqlite_test`; no es instalador operativo. | Fase 2 parcial. |
| Historia de migraciones existente | Soportada y requerida. | Incompatible de forma directa. | Mantener aislada; no reproducirla. |
| Esquema y datos base | Derivados por migraciones MySQL más `app:first-run`. | Crea el esquema ORM actual y registra su baseline; en `sqlite_test`, `app:first-run` usa siete templates locales mínimos. | Fase 2 parcial; seeds operativos pendientes. |
| Geodatos | SQL MySQL específico para desactivar FKs. | Pendiente de adaptador. | Bloqueador de Fase 3. |
| Integridad referencial | Gestionada por InnoDB. | `foreign_keys` verificado por conexión en el perfil de prueba; queda pendiente su diseño operativo. | Fase 1 completada; Fase 3 valida operación. |
| Concurrencia de escrituras | Motor servidor. | Un escritor; diseño exige WAL, timeout y transacciones breves. | Bloqueador de Fase 3. |
| Tests | Carril actual ligado a migraciones upstream. | Prueba enfocada crea un fixture nuevo con el baseline separado; no ejecuta migraciones upstream. | Fase 2, primer corte completado. |
| Backup/restore | Compose incluye herramientas MariaDB. | No definido. | Fuera de Fase 1; obligatorio antes de piloto. |
| Actualizaciones upstream | `git pull` más migraciones MySQL. | Equivalencia SQLite por cada cambio. | Revisión manual por integración. |

## Criterios de entrada para Fase 1

La fase de infraestructura de pruebas puede comenzar sólo cuando todos estos puntos se confirmen:

- [x] SHA upstream, rama local y árbol limpio están registrados.
- [x] Los acoplamientos MySQL conocidos y los comandos SQL directos están inventariados.
- [x] Se decidió no ejecutar la historia de migraciones MySQL en SQLite.
- [x] Se aprobó crear cambios de código exclusivamente en la rama `sqlite-market-validation`.
- [x] Está disponible un entorno de prueba con PHP 8.5.4, Composer 2.9.5 y `pdo_sqlite`, sin tocar la base operacional ni instalar dependencias durante esta fase.
- [x] Se definió `var/sqlite-test/fewohbee.sqlite` como fixture efímera; `var/` está ignorado y la prueba la elimina junto con sus archivos WAL/SHM.
- [x] Fase 1 agregó sólo perfil/fixture/tests RED→GREEN; no creó una base de producción, datos reales ni despliegue.
- [ ] Se acuerda que una falla de concurrencia o integridad detiene el fork y redirige la evaluación a MariaDB.

**Evidencia actualizada:** PHP 8.5.4, Composer 2.9.5, `pdo_sqlite`, `pdo_mysql`,
`intl`, `gd` y `zip` están disponibles, y `vendor/autoload.php` existe. PHP 8.5
cumple el mínimo upstream `>=8.4`.

## Evidencia de Fase 1

- Se añadió el perfil `sqlite_test` para `default` y `geo`: ambas conexiones
  usan `pdo_sqlite` y el mismo archivo efímero bajo `var/sqlite-test/`.
- Un middleware DBAL exclusivo de ese perfil activa `foreign_keys`, WAL y un
  `busy_timeout` de 5000 ms al abrir cada conexión SQLite. El perfil MySQL no
  se modificó.
- La prueba se escribió antes de la configuración. El paso RED falló porque el
  perfil aún no habilitaba `framework.test` (`test.service_container` ausente).
- Tras el cambio mínimo, `php bin/phpunit tests/Functional/SqliteTestProfileTest.php`
  pasó: **1 prueba, 10 aserciones**. No quedaron archivos de fixture, WAL o SHM
  después de la ejecución.
- PHPStan se intentó sobre los tres archivos PHP nuevos, pero no emitió un
  resultado de análisis: el sandbox bloqueó su servidor interno en
  `tcp://127.0.0.1:0`. No se interpreta como aprobación estática.

## Evidencia de Fase 2 — primer corte de baseline

- Se escribió primero `tests/Functional/SqliteBaselineInitializerTest.php`. Su
  paso RED falló porque `App\Sqlite\SqliteBaselineInitializer` no existía.
- `src/Sqlite/SqliteBaselineInitializer.php` crea un baseline sólo si ambas
  conexiones son SQLite y el archivo está vacío. Usa `SchemaTool` sobre los
  metadatos ORM `default` y `geo`; no invoca Doctrine Migrations ni ejecuta
  archivos bajo `migrations/`.
- El baseline registra `sqlite-schema-tool-v1` en
  `fewohbee_sqlite_baseline_metadata`. El prefijo `sqlite_` no puede usarse:
  SQLite lo reserva para sus objetos internos.
- La prueba de fixture fresco verifica tablas requeridas por el arranque
  (`users`, `roles`, `template_types`, `templates`, `objects`, `customers` y
  `guest_categories`), `postal_code_data`, metadato del baseline, ausencia de
  `doctrine_migration_versions`, usuarios iniciales vacíos, FKs y WAL en ambas
  conexiones.
- Comando GREEN: `php bin/phpunit tests/Functional/SqliteTestProfileTest.php
  tests/Functional/SqliteBaselineInitializerTest.php` → **OK (2 pruebas, 26
  aserciones)**. La limpieza dejó sin archivo SQLite/WAL/SHM el fixture.
- Hallazgos: `Subsidiary` usa la tabla histórica `objects`, no
  `subsidiaries`; el `schema_filter` del `default` oculta `postal_code_data`
  en `listTableNames`, por lo que la prueba la verifica en `sqlite_master`.
  `SchemaTool` activa el listener de remember-me y exige un `framework.secret`;
  el perfil de prueba define uno fijo, no sensible.

### Cobertura y límites exactos

- Cubierto: creación de esquema desde mappings ORM actuales, tabla geográfica
  vacía, marcador de versión propio, rechazo de una base SQLite no vacía y
  conservación de FKs/WAL configurados por Fase 1.
- No cubierto: ejecución end-to-end de `app:first-run`; sus `TemplatesFixtures`
  descargan plantillas desde GitHub. Tampoco están cubiertos datos geográficos,
  equivalencia con toda la historia MySQL, migraciones SQLite posteriores,
  importación, concurrencia de reservas/calendario/facturas ni backup/restore.
- Próxima unidad precisa: aislar los seeds de `app:first-run` que no requieren
  red y sustituir la obtención remota de plantillas por una fuente local y
  versionada antes de probar un primer arranque completo sobre el baseline.

## Evidencia de Fase 2 — seeds locales de `app:first-run`

- La prueba RED `tests/Functional/SqliteFirstRunLocalTemplatesTest.php` creó un
  fixture desde el baseline e invocó `app:first-run`. Falló en
  `raw.githubusercontent.com` durante `TemplatesFixtures`, demostrando la
  dependencia remota existente sin descargar contenido.
- En el perfil exclusivo `sqlite_test`,
  `config/services_sqlite_test.yaml` activa `useSqliteLocalTemplates`. El valor
  por defecto permanece en `false`, por lo que el flujo MySQL/MariaDB conserva
  sus descargas upstream.
- `TemplatesFixtures` ahora usa `TemplatesService::importTemplateContents()`
  para siete registros locales, mínimos e idempotentes. Su único contenido es
  la cadena neutral escrita para esta validación: `SQLite market-validation
  local seed`; no se incluyó contenido de `fewohbee-examples` ni de terceros.
- La procedencia está verificada: el repositorio upstream incluye `LICENSE`
  GPL-3.0; los nuevos textos fueron redactados en este fork y no copian
  plantillas remotas.
- Comando GREEN: `php bin/phpunit
  tests/Functional/SqliteFirstRunLocalTemplatesTest.php` → **OK (1 prueba, 7
  aserciones)**. Prueba la creación de siete templates locales mediante
  `app:first-run`, la repetición directa del fixture y
  `--if-not-initialized`, sin red.
  La limpieza dejó sin SQLite/WAL/SHM el fixture.

### Límites del corte de seeds

- Los siete templates son datos técnicos mínimos para validar el flujo, no
  contenido operativo, legal ni de comunicaciones para huéspedes.
- Falta decidir y revisar una fuente local completa, su licencia/procedencia y
  una estrategia de actualización antes de habilitar un piloto. Siguen sin
  cubrir geodatos, equivalencia de migraciones, concurrencia y backup/restore.

## Evidencia de Fase 0

- La rama local `sqlite-market-validation` apunta a `f33b33565939373a16074e0a8fcc5277c75ec0c8`.
- No se editó contenido de `app/`; crear la rama cambió sólo la referencia Git local.
- Se inspeccionaron `config/packages/doctrine.yaml`, `.env.dist`, `config/packages/doctrine_migrations.yaml`, `migrations/`, `src/Command/ImportPostalcodedataCommand.php`, `tests/bootstrap.php` y `bin/run-tests.sh`.

## Próximo paso

Fase 2 debe definir un conjunto local operativo de templates con procedencia y
licencia verificables, o mantener explícitamente estos seeds como sólo prueba.
No ejecutar migraciones MySQL contra SQLite ni tratar el perfil `sqlite_test`
como un instalador o una base de aplicación operativa.
