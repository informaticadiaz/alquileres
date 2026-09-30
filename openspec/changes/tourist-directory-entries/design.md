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

---

# Amendment: Removal Request Form (2026-09-30)

Confirmed user decision: site mail is POSTPONED. The "Removal link" decision above (site contact form plus `init_contact` subject prefill) is SUPERSEDED. The production gate "Mail is verified" (Rollout step 5) and the placeholder-`contactEmail` refusal are REPLACED by "removal channel available". Placeholder mail becomes a warning only. U1–U3 stay deployed; zero entries exist.

## Technical Approach

A plugin-owned public route renders a removal form per entry. It works without email. A valid request inserts a `pending` row and then retires the entry immediately (reversibly) with reason `removal_request`. An admin route lists the requests. The importer treats a blocking request as higher precedence than any seed state or flag. Every decision (validation, throttle, idempotency, admin transitions, schema steps, plan precedence, client IP) lives in the lib under strict TDD. The glue only reads and writes.

## Architecture Decisions

| Topic | Choice | Rejected / why |
|---|---|---|
| Public page | `osc_add_route('tourist-directory-removal', 'directorio/solicitar-baja/([0-9]+)', 'directorio/solicitar-baja/{entry}/', 'tourist-directory/views/removal-form.php')` at plugin load. Routes are registered before `Rewrite::init` (`oc-load.php:326,366`; `Rewrite.php:114-121,152-299`). `osc_route_url` works with and without rewrite (`hDefines.php:1623-1644`). `CWebCustom` renders it inside the theme layout (`controller/custom.php:30,42-44,78`; `sigma/custom.php:20-23`), including flash (`sigma/header.php:143`). The regexp has no `$` anchor, so a query tail cannot break the match. The id comes ONLY from the route param `entry`, never from a form field. | `?page=custom&file=` is deprecated (`custom.php:47`). A theme page: vendor is read-only. |
| POST handler | `init_custom` (`custom.php:30`, before `doModel`). It acts only when `route` is ours and `REQUEST_METHOD==='POST'`. `osc_csrf_check()` runs first; it redirects and exits on failure (`hSecurity.php:85-133`). The form prints `osc_csrf_token_form()` (`:56-63`). Post/Redirect/Get: `osc_redirect_to(route url)` exits (`utils.php:2641`). | Processing inside the view: output has already started. |
| Admin page | `osc_add_route('tourist-directory-admin', 'tourist-directory-admin/?', 'tourist-directory-admin/', 'tourist-directory/admin/requests.php')`. Link: `osc_route_admin_url` (`hDefines.php:1654-1662`) → `renderplugin` renders inside `plugins/view.php` with the admin layout (`oc-admin/plugins.php:432-460`; `omega/plugins/view.php:35-42`). The admin session is enforced by `AdminSecBaseModel`. Moderators are excluded unless granted (`AdminSecBaseModel.php:35-45`). Menu: `admin_menu_init` (`AdminMenu.php:207`) → `osc_admin_menu_plugins` (`hAdminMenu.php:351`). POST actions run in `renderplugin_controller` (`plugins.php:456`, before `doView`) with `osc_csrf_check()` and PRG. A `_configure` hook redirects to the same page. The front controller refuses `/admin/` paths (`custom.php:53`). | `action=admin` + `_configure`: this renders with no layout, and flash messages are unreliable (`plugins.php:421-426`; the tourist-identity README). |
| Table | `t_directory_removal_request`: `pk_i_id`, `fk_i_item_id` INT, `s_seed_id` VARCHAR(191), `s_relation` VARCHAR(16), `s_reply_contact` VARCHAR(190) NULL, `s_reason` VARCHAR(1000) NULL, `s_ip_hash` CHAR(64) NULL, `s_status` VARCHAR(16) (`pending`/`processed`/`rejected`), `dt_requested`, `dt_processed` NULL. Keys on (`fk_i_item_id`), (`s_seed_id`), (`s_ip_hash`,`dt_requested`). The seed id is copied into the row, so precedence survives a lost marker. Blocking statuses: `pending` and `processed`. | FK to the marker table: the same cascade risk as before. |
| Migration | Pref `tourist_directory.schema_version`; target is `2`. `''` means `0`, because `Preference::get` returns `''` for a missing key (`Preference.php:153-158`). `tourist_directory_ensure_schema()` runs from install AND enable. It executes the pure `_schema_steps($stored)` using idempotent `CREATE TABLE IF NOT EXISTS` (step 1 = marker, step 2 = requests). It bumps the pref only after every step succeeds. The same routine generates the pref `ip_salt` (32 random bytes, hex) once. Request paths never run DDL. | DDL on demand in a public request. Relying on install: it will not rerun for the live plugin. |
| Channel availability | `_channel_ready($storedVersion, $tableProbeOk)` requires version ≥ 2 AND `SELECT 1 FROM … LIMIT 1` !== false. If it is not ready, the form shows "temporarily unavailable" and changes nothing. The render hook omits the link when `osc_route_url` returns `''`. | — |
| Decision | Pure `tourist_directory_removal_decide($in)`. Evaluated in order: honeypot `website` filled → `honeypot` (fake success, no write); validation errors → `invalid`; entry marker missing → `not_found` (generic message); blocking request already exists → `already_requested` (same confirmation as success, no insert); per-IP ≥ 5/hour or per-entry ≥ 3/24h → `throttled`; entry already retired → `accept_retired` (insert only); else `accept` (insert, then retire). Insert comes first: if the insert fails, nothing changes. If retire fails after the insert, the request stays pending and visible to the admin. | Retire-first: it produces an unrecorded deactivation. |
| Validation | `relation` ∈ {propietario, administrador, otro} and is required. `reply_contact` ≤ 190 chars, `reason` ≤ 1000 chars, both optional. Values are trimmed, control characters are rejected, and multibyte-safe lengths are used. Input already passes the HTMLPurifier in `Params::getParam` (`Params.php:46-55`), and every value is escaped with `osc_esc_html` on output (public and admin). | — |
| IP | Pure `_client_ip($server)`: use `HTTP_CF_CONNECTING_IP` only when `REMOTE_ADDR` is loopback (the Cloudflare tunnel → 127.0.0.1:8783), otherwise `REMOTE_ADDR`. Stored as `hash_hmac('sha256', ip, ip_salt)`. | `osc_get_ip()` trusts spoofable `Client-IP`/`X-Forwarded-For` headers and mutates `$_SERVER` (`utils.php:2482-2500`). |
| Retention | On the admin page load: set `s_ip_hash` to NULL after 30 days. Blank `reply_contact`/`reason` 180 days after `dt_processed`. The status row is kept forever, for precedence. The privacy note (Ley 25.326) states the purpose, the optional fields, and the retention. | — |
| Admin actions | Pure `_admin_transition($status, $action, $confirm)`: `mark_processed` goes pending→processed. `reactivate` goes pending/processed→rejected and requires `confirm=1`. On reactivate, the glue sets every blocking request of that item to `rejected` and calls `tourist_directory_reactivate($id, ['allow_reactivate'=>true])`. | — |
| Importer precedence | `tourist_directory_plan(..., $flags['removal_seed_ids'])`: a `candidato` whose seed id has a blocking request → `skip`/`removal_requested`. This applies with or without a marker, retired or not, and even with `--allow-reactivate`, so the entry is never created, updated or reactivated. `baja` rows stay unchanged. The plan returns `removal_blocked` ids, and the report prints them. At apply time, each create/update/reactivate re-checks the requests freshly (race guard). `_enable` also excludes items with blocking requests (`NOT EXISTS`). | — |
| Importer gate | `_cli_should_refuse($apply, $installed, $channelReady, $hasDirectoryEmail)`: `--apply` requires `$channelReady`. A dry run is allowed and warns. `_cli_warnings()` reports a placeholder `osc_contact_email()`. `--allow-placeholder-contact` is still parsed as a deprecated no-op. | — |

## Rendering Changes

The `init_contact` prefill is removed. `render_notice_html` links to `osc_route_url('tourist-directory-removal', ['entry'=>$id])`. A `header` hook emits `noindex` on our route. The form shows the entry title, the fixed id, the relation radio, the optional fields, the privacy note, and the hidden honeypot (CSS off-screen, `autocomplete="off"`, `tabindex="-1"`). Both views start with an `ABS_PATH` guard.

## File Changes

| File | Action |
|---|---|
| `plugins/tourist-directory/tourist-directory-lib.php` | Modify: version 0.2.0; schema/channel/validate/decide/limits/client_ip/ip_hash/admin_transition/removal_route; plan precedence; CLI gate and warnings; drop `_removal_url` |
| `plugins/tourist-directory/index.php` | Modify: ensure_schema, salt, routes, `init_custom`/`renderplugin_controller`/`admin_menu_init`/`_configure`, enable exclusion, render link |
| `plugins/tourist-directory/views/removal-form.php`, `admin/requests.php` | Create |
| `plugins/tourist-directory/assets/tourist-directory.css`, `README.md` | Modify |
| `bin/tourist-directory-import.php`, `tests/test_tourist_showcase.php` | Modify |

## Testing Strategy

RED first, in the lib: `_schema_steps` (`''`/1/2), `_channel_ready`, `_validate_removal` (relation enum, lengths, control characters, multibyte), `_removal_decide` (every outcome and its precedence order), the throttle boundaries (4/5, 2/3), `_client_ip` (spoof headers ignored, CF honoured only from loopback), `_ip_hash` (determinism, salt-dependence), `_admin_transition`, plan `removal_requested` with and without a marker and with `allow_reactivate`, `_cli_should_refuse`/`_cli_warnings`, and the route regexp matching `directorio/solicitar-baja/12/` only. Lint: `php -l`. Manual: see Verification.

## Threat Matrix

Routing: Applicable. The new public POST route is protected by CSRF (vendor), the honeypot, the throttle, a route-only id and escaped output. The admin route requires an admin session plus CSRF, and `/admin/` is refused on the front end. Each has RED tests on its pure decision. Shell/subprocess, VCS/PR, executable classification: N/A (none).

## Slicing

- **U6 (~600 lines)**: lib functions and tests, `ensure_schema`/salt/enable exclusion in `index.php`, plan precedence and the CLI gate/warnings, and the importer wiring for gate, report and apply re-check.
- **U7 (~700 lines)**: routes, form view and handler, admin view and handler with its menu, render link, CSS, README.

## Deploy and Verification

1. The agent takes a DB backup.
2. The user syncs `plugins/tourist-directory/` only.
3. In oc-admin, the user runs Disable and then Enable on **Tourist Directory Entries**, NOT on Tourist Portal Identity. This runs the migration.
4. Confirm the menu entry exists and the admin page reports schema 2.
5. Dry-run the importer: it shows no "channel unavailable" warning.
6. Import one test entry with `--apply`.
7. Over HTTPS, the form renders. A missing token is rejected. A valid POST deactivates the entry, and the admin page lists the request as pending. A second POST shows the same confirmation with no new row. The honeypot writes nothing. The 6th POST in an hour is throttled.
8. Admin reactivates the entry with confirm: the request becomes `rejected` and the item is active.
9. A new request, then `--apply --allow-reactivate`: the importer reports `removal_requested` and the entry stays retired.

Rollback: Disable the plugin, which deactivates entries. The requests table is kept, so it never loses precedence.

## Open Questions

- [ ] Are the throttle limits (5/h per IP, 3/24h per entry) and the retention periods (30d/180d) acceptable? Both are lib constants.
- [ ] A legal review of the privacy note text is still pending, like the lawyer review before volume.
