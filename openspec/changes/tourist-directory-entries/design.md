# Design: Tourist Directory Entries

## Technical Approach

New plugin `plugins/tourist-directory/`, following the tourist-identity split: `tourist-directory-lib.php` holds pure, standalone-loadable decisions (TDD), and `index.php` holds Osclass glue. An operator CLI, `bin/tourist-directory-import.php`, stays in the repo and is never synced under the web root. It boots Osclass the way vendor CLI cron does (`index.php:21-39`, `oc-load.php`). Items are created as admin through `Params` + `ItemActions(true)` + `prepareData` + `add()` (`install-functions.php:614-625`). Admin creation sends no emails (`ItemActions.php:311`). The marker table defines what counts as an entry. The guards fail closed.

## Architecture Decisions

| Topic | Choice | Rejected / why |
|---|---|---|
| Marker | Table `t_directory_entry`: `pk_i_id`, `fk_i_item_id` UNIQUE, `s_seed_id` VARCHAR(191) UNIQUE, `s_official_website` VARCHAR(255), `s_fingerprint` CHAR(40), `dt_imported`, `dt_updated`, `dt_retired` NULL, `s_retired_reason` VARCHAR(32) NULL. Created by the install hook with `dao->query('CREATE TABLE IF NOT EXISTS …')`. No FK. | Meta field: it leaks into the public form. FK cascade: an admin hard delete followed by a re-import would silently recreate the entry. |
| Item contact | `contactName` "Directorio público"; `contactEmail` is a per-install pref `tourist_directory.contact_email` = `directorio-<16 hex>@directorio.invalid`, which passes `osc_validate_email` (`hValidate.php:261-313`). `showEmail`/`showPhone` 0. The importer aborts when `User::findByEmail(placeholder)` returns a row, because admin `prepareData` links items to a matching user (`ItemActions.php:1785`). | Site `contactEmail` or a real address: a guard gap would deliver mail. With `.invalid`, even a gap cannot reach the complex. |
| Price | `price=''` gives `i_price` NULL (`ItemActions.php:1859,202`). The NULL price goes through `osc_item_field` as `''` and `osc_item_price()` returns null, which triggers the `item_price_null` filter (`hUtils.php:107-129`, `hItems.php:381-384,1487`). The filter returns `''` only when the current item is an entry. | `0`: it renders "Free" (`hItems.php:1488`). |
| Expiration | `dt_expiration='-1'` as admin gives `''` (`ItemActions.php:1942`). `updateExpirationDate('')` is then a no-op (`Item.php:1199`), which keeps the column default `9999-12-31` (`struct.sql:269`). | Category days: entries would expire silently. |
| Description | Generated factual sentence per locale (es_ES/en_US, whichever are installed). Example: "{nombre} es un {tipo} en {localidad}. Ficha informativa con datos públicos; para consultas y reservas visite el sitio oficial." | Copied text: condition violation. |
| Guards | `init_item` (`controller/item.php:39`) blocks early for `action ∈ {contact, contact_post, send_friend, send_friend_post, add_comment}`. `pre_item_contact_post`/`pre_item_send_friend_post`/`pre_item_add_comment_post` (`controller/item.php:541,628,650`) block again. Both check `(int)Params id` (the same param the actions read, `ItemActions.php:1467,1487,1504`) and the hook's `$item['pk_i_id']`. Lookup is `SELECT` by id with no cache. It blocks on hit, on DB error (`get()===false`), on a Throwable, or when `$item['s_contact_email']` equals the placeholder (this catches orphans). On block: localized flash error ("Esta ficha es información pública de directorio: no admite consultas, envíos ni comentarios. Consulte el sitio oficial del complejo."), then `osc_redirect_to(osc_item_url_ns($id))`, or to `osc_base_url()` when id ≤ 0; the redirect exits (`utils.php:2641`). | Fail-open, or cached lookup. |
| Rendering | `item_title`: label, official link (`rel="nofollow noopener noreferrer" target="_blank"`, scheme re-checked) and removal link. `item_sidebar_top`: short notice. `item_loop_title`: badge. `highlight_class`: echoes `tourist-directory-card `. `show_item` (`controller/item.php:862`) registers a `sigma_bodyClass` filter (`sigma/functions.php:157`) that adds `tourist-directory-entry`, and a false result for `structured_data_show_{footer,header}_filter` (`structured-data.php:56,121`). Those filters suppress Product JSON-LD, the fake rating, price 0, "InStock" and `twitter:site`=contact name. The CSS is enqueued on `init` for the front end only. It hides `#contact`, `.contact_button`, `#comments` and `.price` on entries, and `.currency-value` on cards. Render lookups use one memoized `SELECT fk_i_item_id, s_official_website` per request. | CSS alone is cosmetic; the guards are the enforcement. |
| Removal link | `tourist_directory_removal_url(osc_contact_url(), $id)` appends `tourist_directory_removal=<id>` with the right separator (`hDefines.php:591-597`). `init_contact` validates the id as an entry and pre-fills `Session::_setForm('subject', …)`, which `ContactForm::the_subject` reads (`Contact.form.class.php:92-98`). | Mail link: it exposes an address. |
| Retire | `ItemActions(true)->deactivate($id)` (`ItemActions.php:1174-1198`), plus `dt_retired`/reason. A retired entry is never reactivated unless `--allow-reactivate` is passed. | Delete: out of scope and irreversible. |
| Plugin lifecycle | `_disable` deactivates active entries. `_enable` reactivates entries with `dt_retired IS NULL` (`Plugins.php:494,525`). `_uninstall` runs after deactivate (`Plugins.php:449-454`) and keeps the table and pref, so a reinstall re-links. Install echoes nothing (`Plugins.php:422`). | Drop the table on uninstall: it orphans inactive items with no marker or guard. |
| Dropdown | Add "Apart hotel" and "Complejo de departamentos" to the definitions. Pure `tourist_showcase_merge_options($current,$target)` keeps the existing order and appends missing values. `tourist_showcase_sync_options()` updates `t_meta_fields.s_options` only on a difference, and is called from both plugins' install. The CLI preflight refuses when a mapped type is missing. | Relying on install alone: it only inserts missing fields (`tourist-showcase.php:29-38`). |
| Importer | Dry-run by default. `--apply` writes. `--file=` is required. `--osclass-root=` defaults to `app/osclass`. The importer checks `PHP_SAPI==='cli'`, defines `ABS_PATH` and `CLI`, and sets `OC_ADMIN` false (defaulted at `default-constants.php:22`). It defaults `$_SERVER` `HTTP_HOST`, `REQUEST_URI`, `REMOTE_ADDR` and `SERVER_PORT` (`default-constants.php:47`, `utils.php:2488-2490`). Gate: refuse when `osc_contact_email()` is a placeholder (`.invalid/.test/.example/.localhost`, `localhost`, `example.*`, invalid) unless `--allow-placeholder-contact` is passed. It refuses when the plugin is not installed. At the end it runs `osc_update_cat_stats()` and `osc_cache_flush()`. | Admin button: HTTP timeouts, and it puts seed paths on the web. |

## Seed Contract

Headers are read by name. Only this whitelist is kept: the control key `id` and the routing state `estado_catalogo`, plus the five public fields `nombre, localidad, destino, tipo, web`. Every other column (`contacto`, `notas`, `fuente`, prospect state, …) is dropped when the row is read. For that reason the proposal's `s_source_url` column is omitted. `candidato` with a valid `web` → create, or update when the fingerprint changed. `candidato` without `web` → error. `baja`/`anuncio_propio` → retire when the entry exists. Every other state → skip. Duplicate ids, an unknown `destino` (not a tree leaf, or missing from the `tourist_identity.category_map` pref), a bad `tipo` or a bad URL produce an error row that is skipped. A marker whose item is missing is reported and marked retired (`missing_item`) and is never recreated. Seed ids that are no longer in the file are only reported. Type map: `apart_hotel`→Apart hotel, `complejo_departamentos`→Complejo de departamentos, `departamentos_con_servicios`→Apartamento, `cabanas`→Cabaña.

## Data Flow

    CSV ─→ lib: parse/validate/map ─→ lib: plan(rows, existing, ctx) ─→ glue: Params+ItemActions ─→ t_item (+meta) ─→ t_directory_entry
    POST contact/friend/comment ─→ init_item / pre_*_post ─→ lookup (fresh) ─→ block → flash + osc_redirect_to (exit, utils.php:2641)

## Interfaces / Contracts

Pure (lib): `tourist_directory_version()`, `_seed_columns()`, `_type_map()`, `_parse_rows(array $header, array $rows)`, `_validate_row(array $row, array $leafKeys, array $catMap, int $titleMax)`, `_fingerprint(array $entry)`, `_description($name,$typeKey,$locality,$locale)`, `_item_params(array $entry, array $ctx)` (the full Params map, reset per row), `_plan(array $valid, array $existing, array $flags)`, `_is_placeholder_email($email)`, `_safe_url($url)`, `_removal_url($contactUrl,$id)`, `_should_block(?bool $isEntry, $contactEmail, $placeholder)` (null → block), `_locales(array $installed)`, `_text($es,$en,$locale)`. Glue: install/uninstall/enable/disable, `_lookup_entry(int)` (?bool), `_guard($item=null)`, the render hooks, and `_create/_update/_retire/_reactivate`.

## File Changes

| File | Action |
|---|---|
| `plugins/tourist-directory/{index.php,tourist-directory-lib.php,README.md,assets/tourist-directory.css}` | Create |
| `bin/tourist-directory-import.php` | Create |
| `plugins/tourist-showcase/tourist-showcase{-lib,}.php` | Modify: options, merge, sync |
| `tests/test_tourist_showcase.php` | Modify: require the directory lib and add sections |

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit (RED first) | Merge idempotence, header whitelist, validation, type map, description, fingerprint, plan transitions, placeholder gate, URL safety, removal URL, fail-closed decision | `php tests/test_tourist_showcase.php` |
| Lint | Every file | `php -l` |
| Manual | Dry-run output, then HTTPS render and forged POSTs (a valid CSRF token is required: `controller/item.php:521,591,645`) | User-authorized, test entry only |

## Threat Matrix

N/A for all rows. There is no git/VCS/PR automation, shell or subprocess, and no executable-file classification. The CLI reads one operator-supplied CSV. The request guard is covered by the fail-closed unit tests.

## Migration / Rollout

1. Agent takes a DB backup.
2. User syncs showcase, then directory.
3. User installs tourist-directory in admin.
4. User runs a CLI dry-run.
5. Mail is verified.
6. User runs `--apply`.
7. Agent runs read-only HTTPS checks.

Rollback: disable the plugin (entries are deactivated), revert the options, or restore the backup.

Slicing (the 400-line target):
- U1 (~380): showcase options, and the lib's validation/mapping/description/gate/URL functions with their tests.
- U2 (~400): plugin install, lifecycle, guards, rendering and CSS, plus the guard-decision tests.
- U3 (~330): planner, CLI and README.

## Open Questions

- [ ] Only the `contactEmail` half of the mail gate can be automated. Actually delivering mail must be verified manually before `--apply`.
- [ ] Reactivating a reversed `baja` row requires `--allow-reactivate`. This is deliberate: the spec says it "can" reactivate.
