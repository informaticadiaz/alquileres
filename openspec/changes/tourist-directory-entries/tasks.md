# Tasks: Tourist Directory Entries

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | Original ~1100 (U1 ~380, U2 ~400, U3 ~330; delivered ~2026 actual per apply-progress.md). Amendment adds ~1300 (U6 ~600, U7 ~700). Total plan ~2400. |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | U1 -> U2 -> U3 -> U6 -> U7 |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|------|------|-----------|----------------------|-----------------|-------------------|
| U1 | Showcase dropdown + directory lib pure functions | PR 1 | `php tests/test_tourist_showcase.php` | N/A (pure functions, no Osclass runtime) | Revert dropdown/merge functions in `tourist-showcase-lib.php`; delete `tourist-directory-lib.php` |
| U2 | Plugin install/lifecycle/guards/rendering/CSS | PR 2 | `php tests/test_tourist_showcase.php` | Manual HTTPS render + forged-POST guard check (user-authorized, post-deploy) | Deactivate/uninstall `tourist-directory` — entries deactivated, no items deleted |
| U3 | Import planner + CLI + README | PR 3 | `php tests/test_tourist_showcase.php` | `php bin/tourist-directory-import.php --file=<seed> --osclass-root=app/osclass` (dry-run) | Delete `bin/tourist-directory-import.php`; dry-run only, no production data touched |
| U6 | Amendment: schema migration, salt, removal-decision/validation/throttle/IP lib, admin-transition lib, importer removal precedence + gate/warnings | PR 6 (base: PR 3 / main, stacked-to-main) | `php tests/test_tourist_showcase.php` | N/A (pure functions, no Osclass runtime) | Delete new lib functions; `tourist_directory_ensure_schema()` uses `CREATE TABLE IF NOT EXISTS` only, table stays orphaned but harmless on revert |
| U7 | Amendment: public removal route/form/POST handler, admin route/screen, render link update, CSS, README | PR 7 (base: PR 6) | `php tests/test_tourist_showcase.php` | Manual HTTPS: form render, CSRF/honeypot/throttle rejection, valid submission, admin mark-processed/reactivate (user-authorized, post-deploy) | Disable plugin (deactivates entries); delete route registrations/views; requests table kept (no precedence loss) |

## Phase 1: U1 — Showcase Dropdown + Directory Lib (Pure, TDD)

- [x] 1.1 RED: extend `tests/test_tourist_showcase.php` — two new accommodation-type options and `tourist_showcase_merge_options` idempotence.
- [x] 1.2 GREEN: add options to `tourist_showcase_definitions()`; implement `tourist_showcase_merge_options()`/`tourist_showcase_sync_options()` in `plugins/tourist-showcase/tourist-showcase-lib.php` and `plugins/tourist-showcase/tourist-showcase.php`; call sync from install.
- [x] 1.3 RED: add tests for `tourist_directory_version`, `_seed_columns`, `_type_map`, `_parse_rows`, `_validate_row` (whitelist, dup id, bad destino/tipo/URL).
- [x] 1.4 GREEN: create `plugins/tourist-directory/tourist-directory-lib.php` implementing those five functions.
- [x] 1.5 RED: add tests for `_fingerprint`, `_description` (es_ES/en_US), `_locales`, `_text`, `_is_placeholder_email`, `_safe_url`, `_removal_url`, `_should_block` (null -> block). — `_removal_url` test coverage superseded by Amendment (U6/U7): the page=contact removal link is replaced by a plugin-owned route; see 6.4, 7.1-7.3.
- [x] 1.6 GREEN: implement the eight functions above in `tourist-directory-lib.php`. — `_removal_url` implementation superseded by Amendment (U6): function dropped, see 6.4.
- [x] 1.7 Verify U1: `php tests/test_tourist_showcase.php` green; `php -l` both lib files; commit. (Verified green + linted here; commit is the orchestrator's step, not run by this apply batch.)

## Phase 2: U2 — Plugin Install/Lifecycle/Guards/Rendering/CSS

- [x] 2.1 RED: tests for `_item_params(entry, ctx)` (price NULL, placeholder `contactEmail`, admin owner fields).
- [x] 2.2 GREEN: implement `_item_params` in `tourist-directory-lib.php`.
- [x] 2.3 GREEN: create `plugins/tourist-directory/index.php` — install (`t_directory_entry` table, `tourist_directory.contact_email` pref, options sync), `_enable`/`_disable`/`_uninstall` lifecycle.
- [x] 2.4 GREEN: implement `_lookup_entry(int)`/`_guard($item=null)`; wire to `init_item`, `pre_item_contact_post`, `pre_item_send_friend_post`, `pre_item_add_comment_post`.
- [x] 2.5 GREEN: implement `_create`/`_update`/`_retire`/`_reactivate` (reactivate gated by `--allow-reactivate` in ctx; default: no auto-reactivation).
- [x] 2.6 GREEN: implement render hooks (`item_title`, `item_sidebar_top`, `item_loop_title`, `highlight_class`, `show_item` with `sigma_bodyClass` filter + `structured_data_show_{footer,header}_filter=false`) and `tourist_directory_removal_url()`/`init_contact` prefill. — the `tourist_directory_removal_url()`/`init_contact` page=contact removal link is superseded by Amendment (U7): replaced by a plugin-owned route-based removal form link, see 7.5-7.6.
- [x] 2.7 GREEN: create `plugins/tourist-directory/assets/tourist-directory.css` hiding contact/comment/price chrome; enqueue on `header` (front-end only) — see Deviations for why `header`, not literal `init`.
- [x] 2.8 GREEN: create `plugins/tourist-directory/README.md` (install, lifecycle, guard behavior, CSS scope).
- [x] 2.9 Verify U2: `php tests/test_tourist_showcase.php` green; `php -l` on all new/changed PHP; note guard tests assume `osc_csrf_check()` runs before `pre_item_*_post` hooks; commit is the orchestrator's step, not run by this apply batch.

## Phase 3: U3 — Import Planner + CLI + README

- [x] 3.1 RED: tests for `_plan(valid, existing, flags)` transitions (create/update/skip/retire `missing_item`/reactivate-gated).
- [x] 3.2 GREEN: implement `_plan` in `tourist-directory-lib.php`.
- [x] 3.3 GREEN: create `bin/tourist-directory-import.php` — CLI bootstrap (`ABS_PATH`, `CLI`, `OC_ADMIN` false, default `$_SERVER` keys); flags `--file=`, `--osclass-root=`, `--apply`, `--allow-placeholder-contact`, `--allow-reactivate`.
- [x] 3.4 GREEN: wire production placeholder-contact gate and not-installed refusal; wire dry-run report and `--apply` write path via `Params`+`ItemActions(true)`+`_plan`; call `osc_update_cat_stats()`/`osc_cache_flush()` after apply. — the contactEmail hard gate is superseded by Amendment (U6): `--apply` now requires the removal channel (`_channel_ready`), and a placeholder `contactEmail` only warns; see 6.15-6.16.
- [x] 3.5 GREEN: document CLI usage, flags, seed contract in `plugins/tourist-directory/README.md` and the CLI header comment.
- [x] 3.6 Verify U3: `php tests/test_tourist_showcase.php` green; `php -l bin/tourist-directory-import.php`; commit is the orchestrator's step, not run by this apply batch.

## Phase 4: Dry-Run Only (Production Import Gated)

- [x] 4.1 Manual dry-run: `php bin/tourist-directory-import.php --file=<seed> --osclass-root=app/osclass` (no `--apply`) against a sample of real seed rows; record output. Original note ("`--apply` stays blocked until site mail works") is superseded by Amendment (U6/U7): site mail is postponed indefinitely; `--apply` now unblocks once the removal channel exists (schema_version 2) — see Phase 8.

## Phase 5: Deployment [USER][AUTH REQUIRED]

- [x] 5.1 Agent: pre-deploy `mysqldump` backup into `data/osclass/backups/`.
- [x] 5.2 [USER] Sync `plugins/tourist-showcase/{tourist-showcase.php,tourist-showcase-lib.php}` flat into `app/osclass/oc-content/plugins/` (read-only) FIRST.
- [x] 5.3 [USER] Sync `plugins/tourist-directory/` into `app/osclass/oc-content/plugins/tourist-directory/` (read-only) via `diff -r`.
- [x] 5.4 [USER][AUTH REQUIRED] Install `tourist-directory` from oc-admin.
- [ ] 5.5 Agent: read-only HTTPS verification — render, no price, guards, dropdown values.
- [ ] 5.6 [USER][AUTH REQUIRED] Optional smoke test: create one test entry, forge CSRF-valid POSTs over HTTPS to confirm guard rejection, then deactivate the test entry. — superseded by Amendment (U7): the real import and mandatory removal-flow/forged-POST verification now happen in Phase 8 (8.5-8.7), which subsumes this optional smoke test.

## Phase 6: U6 — Amendment: Schema Migration, Removal-Decision Lib, Importer Precedence (Pure, TDD)

- [x] 6.1 RED: tests for `_schema_steps($stored)` transitions (`''`/`0` -> steps 1+2; `1` -> step 2 only; `2` -> no-op).
- [x] 6.2 GREEN: implement `_schema_steps()` and `tourist_directory_ensure_schema()` in `tourist-directory-lib.php`/`index.php` — idempotent `CREATE TABLE IF NOT EXISTS` for `t_directory_removal_request` (step 2), bump `tourist_directory.schema_version` pref only after all steps succeed, generate `tourist_directory.ip_salt` pref once (32 random bytes, hex); call from install AND enable.
- [x] 6.3 RED: tests for `_channel_ready($storedVersion, $tableProbeOk)`.
- [x] 6.4 GREEN: implement `_channel_ready()`; remove `_removal_url()` (superseded, see 1.5/1.6) and its call sites.
- [x] 6.5 RED: tests for `_validate_removal($in)` — relation enum (propietario/administrador/otro, required), `reply_contact` <=190 chars, `reason` <=1000 chars, control-character rejection, multibyte-safe length.
- [x] 6.6 GREEN: implement `_validate_removal()`.
- [x] 6.7 RED: tests for `_client_ip($server)` (spoofed `Client-IP`/`X-Forwarded-For` ignored; `HTTP_CF_CONNECTING_IP` honored only when `REMOTE_ADDR` is loopback) and `_ip_hash($ip, $salt)` (determinism, salt-dependence).
- [x] 6.8 GREEN: implement `_client_ip()` and `_ip_hash()` (`hash_hmac('sha256', ip, ip_salt)`).
- [x] 6.9 RED: tests for `_removal_decide($in)` covering every outcome in precedence order: honeypot filled -> `honeypot`; validation error -> `invalid`; missing marker -> `not_found`; existing blocking request -> `already_requested`; throttle boundaries per-IP (4 ok / 5th throttled within an hour) and per-entry (2 ok / 3rd throttled within 24h) -> `throttled`; already retired -> `accept_retired`; else -> `accept`.
- [x] 6.10 GREEN: implement `_removal_decide()` with the exact precedence order above.
- [x] 6.11 RED: tests for `_admin_transition($status, $action, $confirm)` — `mark_processed` pending->processed; `reactivate` pending/processed->rejected requires `confirm=1`; invalid status/action combinations rejected.
- [x] 6.12 GREEN: implement `_admin_transition()`.
- [x] 6.13 RED: tests for `tourist_directory_plan()` with `$flags['removal_seed_ids']` — `candidato` rows with a blocking seed id -> skip `removal_requested`, with and without a marker, retired or not, and even with `allow_reactivate` set; `baja` rows unaffected.
- [x] 6.14 GREEN: update `tourist_directory_plan()` to check `removal_seed_ids` before any other state, returning `removal_blocked` ids for the report.
- [x] 6.15 RED: tests for `_cli_should_refuse($apply, $installed, $channelReady, $hasDirectoryEmail)` (apply requires `channelReady`; dry-run never gated) and `_cli_warnings()` (placeholder `osc_contact_email()` warning).
- [x] 6.16 GREEN: replace the contactEmail hard gate in `_cli_should_refuse()` with the channel-availability gate (superseded, see 3.4); add `_cli_warnings()`; keep `--allow-placeholder-contact` parsed as a deprecated no-op.
- [x] 6.17 GREEN: wire `bin/tourist-directory-import.php` to re-check blocking requests fresh at apply time for each create/update/reactivate row (race guard) and print `removal_blocked` ids in the report.
- [x] 6.18 GREEN: wire `tourist_directory_enable()` to exclude items with a blocking removal request (`NOT EXISTS`) from auto-reactivation.
- [x] 6.19 Verify U6: `php tests/test_tourist_showcase.php` green; `php -l` on `tourist-directory-lib.php`, `index.php`, `bin/tourist-directory-import.php`, test file; local commit.

## Phase 7: U7 — Amendment: Public Removal Route/Form, Admin Screen, Render Link, CSS, README

- [x] 7.1 RED: test for the removal-route regexp (exposed as a lib constant/pure helper) matching `directorio/solicitar-baja/12/` and rejecting malformed/non-numeric paths.
- [x] 7.2 GREEN: implement the regexp as a shared lib constant; register `osc_add_route('tourist-directory-removal', 'directorio/solicitar-baja/([0-9]+)', 'directorio/solicitar-baja/{entry}/', 'tourist-directory/views/removal-form.php')` and `osc_add_route('tourist-directory-admin', 'tourist-directory-admin/?', 'tourist-directory-admin/', 'tourist-directory/admin/requests.php')` at plugin load in `index.php`.
- [x] 7.3 GREEN: create `plugins/tourist-directory/views/removal-form.php` — `ABS_PATH` guard, entry id from the route param only (never a form field), relation radio (required), optional `reply_contact`/`reason`, Ley 25.326 privacy note, `osc_csrf_token_form()`, hidden honeypot (CSS off-screen, `autocomplete="off"`, `tabindex="-1"`), "temporarily unavailable" message when `_channel_ready()` is false.
- [x] 7.4 GREEN: implement the `init_custom` POST handler in `index.php` — acts only when route is ours and `REQUEST_METHOD==='POST'`; `osc_csrf_check()` first; builds `_removal_decide()` input including `_client_ip()`/`_ip_hash()`; on `accept`/`accept_retired`, inserts the request row THEN retires the entry (insert first); PRG via `osc_redirect_to()`.
- [x] 7.5 GREEN: update the render hooks — link to `osc_route_url('tourist-directory-removal', ['entry'=>$id])`, omitting the link when `osc_route_url()` returns `''`; remove the `init_contact` prefill (superseded, see 2.6).
- [x] 7.6 GREEN: add a `header` hook emitting `noindex` on the removal and admin routes.
- [x] 7.7 GREEN: create `plugins/tourist-directory/admin/requests.php` — lists requests (entry name, date, relation, status; reply contact visible only here); `mark_processed` and `reactivate` (requires `confirm=1`) actions.
- [x] 7.8 GREEN: wire the admin POST handling in `renderplugin_controller` with `osc_csrf_check()` + PRG; on `reactivate`, set every blocking request for that item to `rejected` and call `tourist_directory_reactivate($id, ['allow_reactivate'=>true])`.
- [x] 7.9 GREEN: wire `admin_menu_init` to add the "Tourist Directory Entries" admin menu entry via `osc_admin_menu_plugins`.
- [x] 7.10 GREEN: retention housekeeping on the admin page load — null `s_ip_hash` after 30 days; blank `reply_contact`/`reason` 180 days after `dt_processed`.
- [x] 7.11 GREEN: update `plugins/tourist-directory/assets/tourist-directory.css` for the removal form (honeypot off-screen styling) and admin screen.
- [x] 7.12 GREEN: update `plugins/tourist-directory/README.md` — routes, schema migration, throttle/retention defaults, admin usage.
- [x] 7.13 Verify U7: `php tests/test_tourist_showcase.php` green; `php -l` on all new/changed PHP; local commit is the orchestrator's step, not run by this apply batch.

## Phase 8: Amendment Deployment [USER][AUTH REQUIRED]

- [ ] 8.1 Agent: pre-deploy `mysqldump` backup into `data/osclass/backups/`, before the schema migration.
- [ ] 8.2 [USER] Sync `plugins/tourist-directory/` into `app/osclass/oc-content/plugins/tourist-directory/` (read-only) via `diff -r`.
- [ ] 8.3 [USER][AUTH REQUIRED] In oc-admin, Disable then Enable **Tourist Directory Entries** (explicitly NOT "Tourist Portal Identity") to run the schema migration.
- [ ] 8.4 Agent: read-only checks — `tourist_directory.schema_version` pref is `2`, `t_directory_removal_request` table exists, and both routes are reachable over HTTPS, including the pretty-URL form.
- [ ] 8.5 [USER][AUTH REQUIRED] Run the real import: `php bin/tourist-directory-import.php --file=data/prospeccion/seed/complejos.csv --apply` (now allowed since the removal channel exists; a placeholder `contactEmail` only warns).
- [ ] 8.6 Agent: HTTPS verification of entries — label, no price, no contact UI, removal form renders and works; guards block CSRF-valid forged POSTs (forged POSTs only with explicit user authorization).
- [ ] 8.7 [USER][AUTH REQUIRED] Test removal request on one entry over HTTPS, confirm it deactivates and appears pending in the admin screen, then admin reactivates it with `confirm=1` to verify the explicit-reactivation path.
