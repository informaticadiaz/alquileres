# FewohBee — SQLite market-validation fork

This repository is a fork of [FewohBee](https://github.com/developeregrem/fewohbee)
(GPL-3.0) that adds an **experimental SQLite profile** for small, single-host
installations with low write concurrency: a guesthouse or a few holiday
apartments that want to try the product without running MySQL/MariaDB.

The upstream MySQL/MariaDB path is unchanged and remains the supported
baseline and the migration route. See `README.md` for the upstream project.

> **Status: experimental.** Use fictitious data until you have tested backups,
> restores and your own workload. There is no verified export to MariaDB yet.

## What the fork adds

All changes live on top of upstream commit `f33b335` and are scoped to two
Symfony environments: `sqlite_test` (functional tests) and `sqlite_prod`
(operation). Other environments behave exactly as upstream.

| Area | Change |
| --- | --- |
| Connections | `pdo_sqlite` for `default` and `geo` (one file), with `foreign_keys`, WAL and a 5 s `busy_timeout` on every connection. |
| Transactions | Every top-level DBAL transaction starts with `BEGIN IMMEDIATE`, so writers are serialized instead of acting on stale snapshots. |
| Concurrency fixes | Public booking re-checks availability and saves in one transaction (no overbooking); invoice journal entries, month batches and iCal imports are created atomically; scheduled workflows take a process lock. |
| Dates | Date columns are stored as `Y-m-d 00:00:00` so SQLite's text comparison matches DQL `DateTime` parameters; repositories bind typed date parameters. |
| Schema | A baseline built from the entity mappings (`app:sqlite:init`) instead of the MySQL migration history; a separate migration line in `migrations-sqlite/`. |
| First run | Local technical templates instead of downloading them. |
| Backups | `app:sqlite:backup` writes a consistent, verified single-file copy with `VACUUM INTO`; `--dir`/`--keep` for scheduled runs with retention. |
| Operation | `sqlite_prod` inherits upstream `when@prod` settings, runs with debug disabled, stores sessions under `var/sessions/`, throttles logins (5 per 15 min) and sends basic security headers. |
| Spanish | A Spanish interface translated from the English files (`translations/**/*.es.yaml`), enabled as locale `es` with English as its fallback. Select it with `LOCALE=es`. |

Known limitations:

- The registration book module (slated for removal upstream) still compares
  dates as plain text on SQLite.
- Invoice numbers are checked and saved in two steps; two users saving at the
  same instant could produce a duplicate number.
- Upstream schema changes need a hand-written SQLite migration in
  `migrations-sqlite/`; `doctrine:schema:validate` must stay clean.

## Running the SQLite profile

Requirements: PHP 8.4+ with `pdo_sqlite` and `intl`, Composer.

```bash
# In a dedicated deploy checkout, not your development checkout.
composer install --no-dev --no-scripts --optimize-autoloader
set -a; . ~/.config/fewohbee/fewohbee.env; set +a   # see deploy/sqlite/fewohbee.env.example
php bin/console cache:clear
php bin/console importmap:install
php bin/console asset-map:compile
php bin/console app:sqlite:init
php bin/console app:first-run   # creates the first admin user
```

`deploy/sqlite/` contains example systemd **user** units: the application on
`127.0.0.1` with PHP's built-in server, and a daily verified backup timer.
Expose it through a reverse proxy or tunnel that terminates TLS and set
`TRUSTED_PROXIES` to that proxy's address only. PHP's built-in server is
suitable for a small demo, not for high traffic.

Updating: back up with `app:sqlite:backup <file>`, check out the new commit,
reinstall dependencies, clear the cache, compile assets, run
`php bin/console doctrine:migrations:migrate` and restart the service.

Restoring: stop the service, remove the database file and its `-wal`/`-shm`
files, copy the backup in place and start the service again.

## Spanish translation

Set `LOCALE=es` to use it. Every `*.en.yaml` has a `*.es.yaml` sibling;
`tests/Unit/Translation/SpanishTranslationParityTest.php` fails when keys,
placeholders, plural intervals or HTML tags differ, so new upstream English
keys must be translated before they pass. Style: neutral Spanish addressing
the user informally (tú).

Not covered yet: e-mail and PDF templates created by users, the default
customer salutations stored in the settings, and texts outside
`translations/`.

## Tests

```bash
php bin/phpunit tests/Functional --filter Sqlite   # SQLite profile, no database server needed
php bin/phpunit tests/Unit
```

Do not run `bin/run-tests.sh` for the SQLite tests: it prepares a MySQL test
database.

## License

GPL-3.0, like upstream. See `LICENSE`.
