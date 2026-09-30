# Apply Progress: tourist-directory-entries

## Scope covered so far

Phase 1 (U1), Phase 2 (U2), and Phase 3 (U3: import planner + CLI + README) are complete. Phase 4
(manual dry-run) and Phase 5 (deployment, [USER][AUTH REQUIRED]) are NOT started -- both are
explicitly out of scope for an apply batch (Phase 4 requires the real Osclass/DB, Phase 5 is
[USER][AUTH REQUIRED]). No commit made in any batch (commit is the orchestrator's step, not run by
this apply batch). No `app/` file was ever written, only read for citation verification.
`data/prospeccion/*` was never read in any batch; the U3 sample dry-run used a synthetic in-memory
fixture through the pure planner only.

## Completed Tasks

### Phase 1 (U1) — carried over from the prior apply batch, unchanged
- [x] 1.1 RED: extended `tests/test_tourist_showcase.php` with dropdown vocabulary + `tourist_showcase_merge_options` idempotence tests.
- [x] 1.2 GREEN: added "Apart hotel"/"Complejo de departamentos" to `tourist_showcase_definitions()`; implemented pure `tourist_showcase_merge_options()` in `tourist-showcase-lib.php`; implemented glue `tourist_showcase_sync_options()` in `tourist-showcase.php`, called from `tourist_showcase_install()`.
- [x] 1.3 RED: added tests for `tourist_directory_version`, `_seed_columns`, `_type_map`, `_parse_rows`, `_validate_row` (whitelist, dup id, unknown destino incl. missing from category map, unknown tipo, unsafe URL, missing web, invalid title).
- [x] 1.4 GREEN: created `plugins/tourist-directory/tourist-directory-lib.php` implementing those five functions.
- [x] 1.5 RED: added tests for `_fingerprint`, `_description` (es_ES/en_US), `_locales`, `_text`, `_is_placeholder_email`, `_safe_url`, `_removal_url`, `_should_block` (null -> block, true -> block, orphan false+placeholder-match -> block, normal false -> no block).
- [x] 1.6 GREEN: implemented the eight functions above in `tourist-directory-lib.php`.
- [x] 1.7 Verify U1: full suite green; `php -l` clean on both lib files, `tourist-showcase.php`, and the test file.

### Phase 2 (U2) — this batch
- [x] 2.1 RED: added tests for `tourist_directory_item_params(entry, ctx)` — price `''`, placeholder `contactEmail`, `contactName`, `showEmail`/`showPhone`, `dt_expiration='-1'`, `catId`, per-locale `title`/`description`, and a single-locale case restricting output to only the requested locale.
- [x] 2.2 GREEN: implemented `tourist_directory_item_params()` in `tourist-directory-lib.php`.
- [x] 2.3 GREEN: created `plugins/tourist-directory/index.php` — `tourist_directory_install()` (creates `t_directory_entry` via `CREATE TABLE IF NOT EXISTS`, generates the per-install placeholder contact email once, syncs showcase options), `tourist_directory_enable()`/`tourist_directory_disable()`/`tourist_directory_uninstall()`.
- [x] 2.4 GREEN: implemented `tourist_directory_lookup_entry(int)` (fresh, uncached, tri-state) and `tourist_directory_guard($item=null)`; wired to `init_item` (action-filtered), `pre_item_contact_post`, `pre_item_send_friend_post`, `pre_item_add_comment_post`.
- [x] 2.5 GREEN: implemented `tourist_directory_create/_update/_retire/_reactivate` in `index.php` (reactivate gated by `$ctx['allow_reactivate']`, default false — never auto-reactivates).
- [x] 2.6 GREEN: implemented render hooks (`item_title` dual-use-safe, `item_sidebar_top`, `item_loop_title`, `highlight_class`, `show_item` registering `sigma_bodyClass` + both `structured_data_show_{footer,header}_filter=false`), `tourist_directory_removal_url()` reuse via the lib, and `init_contact` subject prefill.
- [x] 2.7 GREEN: created `plugins/tourist-directory/assets/tourist-directory.css`; enqueued via the `header` hook (see Deviations), front-end only (`OC_ADMIN` guard).
- [x] 2.8 GREEN: created `plugins/tourist-directory/README.md`.
- [x] 2.9 Verify U2: full suite green (all U1 + U2 + pre-existing tourist-identity/tourist-showcase assertions); `php -l` clean on `index.php`, `tourist-directory-lib.php`, and the test file.

### Phase 3 (U3) — this batch
- [x] 3.1 RED: added tests for `tourist_directory_plan(valid, existing, flags)` covering create,
  noop, update, reactivate-gated skip, reactivate (allowed), retire `missing_item` (active and
  already-retired), retire `baja`, retire `anuncio_propio`, noop-already-retired, skip `no_entry`,
  skip `not_importable`, and `not_in_seed` reporting; and for `tourist_directory_cli_parse_args`
  (all flags, defaults, unknown-flag capture) and `tourist_directory_cli_should_refuse` (dry-run
  never gated, `--apply` gated/overridden by placeholder contact email, not-installed refusal).
- [x] 3.2 GREEN: implemented `tourist_directory_plan()` (+ two private helpers
  `tourist_directory_plan_missing_item()`/`tourist_directory_plan_candidato()`/
  `tourist_directory_plan_retirement()`), `tourist_directory_cli_parse_args()`, and
  `tourist_directory_cli_should_refuse()` in `tourist-directory-lib.php`.
- [x] 3.3 GREEN: created `bin/tourist-directory-import.php` (repo root, never synced under `app/`)
  — CLI bootstrap mirroring `app/osclass/index.php:19-25`/`oc-load.php` (defines `ABS_PATH`/`CLI`,
  defaults `$_SERVER` HTTP_HOST/REQUEST_URI/REMOTE_ADDR/SERVER_PORT, requires `oc-load.php`, never
  redefines `OC_ADMIN` — it stays at its `default-constants.php:22-23` default of `false`); flags
  `--file=`, `--osclass-root=` (default `app/osclass`), `--apply`, `--allow-placeholder-contact`,
  `--allow-reactivate` via `tourist_directory_cli_parse_args()`.
- [x] 3.4 GREEN: wired the production placeholder-contact gate and not-installed refusal via
  `tourist_directory_cli_should_refuse()` (installed detected via
  `function_exists('tourist_directory_contact_email')` — true only once the *deployed* plugin copy
  was loaded by `Plugins::init()` inside `oc-load.php`); wired the mandatory auto-link guard
  (`User::newInstance()->findByEmail(tourist_directory_contact_email())`, checked once before any
  write); wired the dry-run report (`tourist_directory_cli_print_report()` — seed id + nombre only,
  per action) and the `--apply` write path via the existing `tourist_directory_create/_update/
  _retire/_reactivate()` glue from `index.php` driven by `tourist_directory_plan()`'s actions; calls
  `osc_update_cat_stats()`/`osc_cache_flush()` once after the apply loop.
- [x] 3.5 GREEN: documented CLI usage/flags/seed contract/gates in the CLI's own header comment and
  added an "Importer CLI" section to `plugins/tourist-directory/README.md`.
- [x] 3.6 Verify U3: full suite green (`php tests/test_tourist_showcase.php` → "Tourist showcase
  checks passed.", exit 0); `php -l` clean on `bin/tourist-directory-import.php`,
  `tourist-directory-lib.php`, `index.php`, and the test file; commit is the orchestrator's step,
  not run by this apply batch.

## Files Changed (this batch, U2)

| File | Action | Lines |
|---|---|---|
| `plugins/tourist-directory/index.php` | Created | 622 |
| `plugins/tourist-directory/assets/tourist-directory.css` | Created | 75 |
| `plugins/tourist-directory/README.md` | Created | 85 |
| `plugins/tourist-directory/tourist-directory-lib.php` | Modified (+`tourist_directory_item_params`) | +40 |
| `tests/test_tourist_showcase.php` | Modified (+U2 RED assertions) | +42 |

Total authored for this batch: ~864 changed lines. Combined with U1's ~463, total so far ~1327,
above the original ~1100 total estimate but consistent with the already-resolved `auto-chain` /
`stacked-to-main` delivery strategy (tasks.md: "Decision needed before apply: No"); each unit (U1,
U2) is its own deliverable PR slice.

## Files Changed (this batch, U3)

| File | Action | Lines |
|---|---|---|
| `bin/tourist-directory-import.php` | Created | 323 |
| `plugins/tourist-directory/tourist-directory-lib.php` | Modified (+`tourist_directory_plan()` + 3 private helpers, +`tourist_directory_cli_parse_args()`, +`tourist_directory_cli_should_refuse()`) | +158 |
| `plugins/tourist-directory/README.md` | Modified (+"Importer CLI" section) | +57/-3 |
| `tests/test_tourist_showcase.php` | Modified (+U3 RED assertions: plan transitions, CLI arg parsing, mail gate) | +164 |

Total authored for this batch: 323 + 158 + 54 (net README) + 164 = 699 changed lines (`git diff
--stat` for the 3 modified files: 376 insertions/deletions, plus the new 323-line file), within the
runtime ledger's 800-line cap for this work unit — no `size:exception` needed for U3, unlike U2.
Combined with U1 (~463) and U2 (~864), running total so far ~2026, all consistent with the
already-resolved `auto-chain`/`stacked-to-main` decision (each unit is its own deliverable PR slice).

## TDD Cycle Evidence (U2)

| Task | Test section | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|---|---|---|
| 2.1/2.2 `tourist_directory_item_params` | "Phase 2.1/2.2 - item_params" | Unit | Confirmed — full suite green before the change (baseline, includes every U1 assertion) | Confirmed — ran suite, fatal `Call to undefined function tourist_directory_item_params()` at test line 933 | Confirmed — full suite green after implementing the function in the lib | 2 cases: full two-locale entry (price/contactEmail/contactName/showEmail/showPhone/dt_expiration/catId/title/description) + single-locale ctx restricting output keys | None needed — function already minimal/clean |

`_create/_update/_retire/_reactivate`, the render hooks, and the lifecycle functions (2.3–2.8) are
Osclass-bound glue with no Osclass runtime available to this test harness (plain PHP assertion
script, no DB/bootstrap — same constraint U1 already documented for `tourist_showcase_sync_options`);
they are verified by `php -l` and by the vendor file:line citations below, not by unit tests. This
matches the established pattern for glue functions in this plugin family (see
`tourist_showcase_sync_options()`, `tourist_identity_apply_tree()`, etc., none of which have direct
unit tests either).

### Test Summary (U2)
- Total tests (assertions) written this batch: 9 new `expect_true` assertions.
- Total tests passing: all (full suite, 55 new assertions across U1+U2 combined, plus every
  pre-existing tourist-showcase/tourist-identity assertion).
- Layers used: Unit only. No integration/E2E layer exists for this stack.
- Pure functions created this batch: 1 (`tourist_directory_item_params`).
- Glue functions created this batch (index.php, not unit-testable without an Osclass runtime): 33.

## TDD Cycle Evidence (U3)

| Task | Test section | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|---|---|---|
| 3.1/3.2 `tourist_directory_plan` | "Phase 3.1/3.2 - plan transitions" | Unit | Confirmed — full suite green before the change (baseline, includes every U1+U2 assertion) | Confirmed — ran suite, fatal `Call to undefined function tourist_directory_plan()` at test line 1013 | Confirmed — full suite green after implementing `tourist_directory_plan()` + 3 private helpers in the lib | 12 cases in one call: create, noop, update, reactivate-gated skip, reactivate (allowed, separate call), missing_item (active + already-retired), retire baja, retire anuncio_propio, noop-already-retired, skip no_entry, skip not_importable, not_in_seed reporting, plus a default-flags call | None needed — the missing_item top-level gate was refactored out of the candidato/retirement branches during initial GREEN (not a separate pass) to avoid duplicating the check in both |
| 3.3/3.4 `tourist_directory_cli_parse_args` / `tourist_directory_cli_should_refuse` | "Phase 3.3/3.4 - CLI argument parsing and production mail gate" | Unit | Confirmed — full suite green before the change | Confirmed — ran suite, fatal `Call to undefined function tourist_directory_cli_parse_args()` at test line 1070 | Confirmed — full suite green after implementing both functions | `parse_args`: 3 cases (every flag set, defaults, unknown-flag capture). `should_refuse`: 6 cases (dry-run never gated, apply+placeholder blocks, apply+placeholder+override passes, apply+real-email passes, not-installed blocks dry-run too, not-installed blocks apply too) | None needed — both functions already minimal |

`bin/tourist-directory-import.php` itself (CLI bootstrap, CSV I/O, DB marker/item-existence queries,
the `--apply` write loop) is Osclass-bound glue with no Osclass runtime available to this test
harness — same constraint U1/U2 already documented. It is verified by `php -l`, by a synthetic
dry-run exercised through the pure planner only (see Work Unit Evidence below — `data/prospeccion/*`
was never read), and by the vendor `file:line` citations in this file.

### Test Summary (U3)
- Total tests (assertions) written this batch: 27 new `expect_true` assertions (12 `plan()` action
  assertions read from one shared result map + 1 `not_in_seed` assertion + 1 separate reactivate-call
  assertion + 1 default-flags assertion + 3 `parse_args` assertions + 6 `should_refuse` assertions,
  plus the loop-free literal counts above — 27 total `expect_true` calls added).
- Total tests passing: all (full suite: every U1+U2 assertion plus these 27, all green).
- Layers used: Unit only. No integration/E2E layer exists for this stack.
- Pure functions created this batch: 6 (`tourist_directory_plan`, `tourist_directory_plan_missing_item`,
  `tourist_directory_plan_candidato`, `tourist_directory_plan_retirement`,
  `tourist_directory_cli_parse_args`, `tourist_directory_cli_should_refuse`).
- Glue functions created this batch (`bin/tourist-directory-import.php`, not unit-testable without an
  Osclass runtime): the CLI bootstrap, CSV read/parse wiring, existing-marker/item-existence DB
  queries, `tourist_directory_cli_print_report()`, `tourist_directory_cli_print_usage()`, the
  auto-link guard check, and the `--apply` write loop.

## Work Unit Evidence (Hard Gate, all modes)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `php tests/test_tourist_showcase.php` → `Tourist showcase checks passed.`, exit 0 (covers U1+U2+U3) |
| Runtime harness command/scenario and exact result | N/A for U3 in this batch, by explicit scope instruction: the manual dry-run against the real Osclass/DB is Phase 4 (tasks.md 4.1), and Phase 5 deployment is `[USER][AUTH REQUIRED]` — neither runs in this sandbox and neither was attempted; `data/prospeccion/*` was never read. Instead, a synthetic in-memory fixture (invented ids `demo-1..4`, none of them real seed data) was run through the pure planner only (`tourist_directory_parse_rows` → `tourist_directory_validate_row` → `tourist_directory_plan`, no Osclass/DB) and produced the expected `create`/`update`/`retire (baja)`/`unknown_destino` classification, confirming the planner's output shape end-to-end before any glue wiring is trusted. Every CLI bootstrap/guard/hook claim is grounded in exact vendor `file:line` citations read directly from `app/osclass/` this session (see below). |
| Rollback boundary | Delete `bin/tourist-directory-import.php`; revert the `tourist_directory_plan()`/`tourist_directory_cli_parse_args()`/`tourist_directory_cli_should_refuse()` additions (+3 private plan helpers) in `tourist-directory-lib.php`, the "Importer CLI" section in `plugins/tourist-directory/README.md`, and the U3 test sections in `tests/test_tourist_showcase.php`. No DB/runtime state was created or touched (the CLI was never executed against a live Osclass/DB in this batch). |

## U2's flagged `ItemActions::edit()` verification (resolved this batch)

U2's apply-progress flagged: *"`_update`'s exact `ItemActions::edit()` return-value contract was not
exhaustively read... before U3 wires the importer's update path."* `ItemActions.php:708-936` (the
full `edit()` method) was read in full this batch. Findings:

- **Return contract confirmed identical in shape to `add()`**: `$success = $flash_error` (a non-empty
  string) on validation failure, else `$success = $result` where
  `$result = $this->manager->update($aUpdate, ['pk_i_id'=>..., 's_secret'=>...])` (`ItemActions.php:
  868-871,932`) — never a string on the success path. `tourist_directory_update()`'s existing
  `is_string($result)` check (written in U2, unchanged this batch) is therefore correct.
- **New finding, not anticipated by design.md**: `edit()` contains its own `userId`-based contact
  overwrite (`ItemActions.php:829-835`: `if ($aItem['userId'] != '') { $aItem['contactName'] =
  $user['s_name']; $aItem['contactEmail'] = $user['s_email']; }`), independent of the overwrite
  already inside `prepareData()` (`ItemActions.php:1806-1810`, U2's cited auto-link risk). Traced the
  full chain: `prepareData()` sets `$aItem['userId']` from
  `User::newInstance()->findByEmail(Params::getParam('contactEmail'))` (`ItemActions.php:1785-1788`);
  when no user owns that email, `$userId` stays `null`, and PHP's `null != ''` evaluates `false`, so
  `edit()`'s own overwrite block at 829-835 never executes either. **Conclusion: a single guard is
  sufficient** — as long as no registered user owns `tourist_directory_contact_email()`'s exact
  address, both `prepareData()`'s and `edit()`'s independent overwrite paths stay inert. This is
  exactly why `bin/tourist-directory-import.php` checks `User::newInstance()->findByEmail(...)` once,
  globally, before *any* write (create, update, retire, or reactivate) — not narrowly scoped to
  create alone, since `edit()` carries the same risk through a second, independent code path.
- No email is sent by `edit()` in any branch (no `sendEmails()` call anywhere in the method), matching
  `add()`'s admin-only suppression (`ItemActions.php:311-313`).

## Vendor bootstrap citations for `bin/tourist-directory-import.php` (verified this batch)

| Bootstrap step | This CLI | Vendor precedent |
|---|---|---|
| Define `ABS_PATH` | `define('ABS_PATH', $osclassRoot)` from `--osclass-root` | `app/osclass/index.php:19`: `define('ABS_PATH', str_replace(...))` |
| Define `CLI` | `define('CLI', true)` unconditionally (this script is always CLI-only, gated by the `PHP_SAPI !== 'cli'` check above it) | `app/osclass/index.php:21-23`: `if(PHP_SAPI === 'cli') { define('CLI', true); }` |
| Require the loader | `require_once ABS_PATH . 'oc-load.php'` | `app/osclass/index.php:25`: `require_once ABS_PATH . 'oc-load.php';` |
| `OC_ADMIN` | Never defined by this script | `app/osclass/oc-includes/osclass/default-constants.php:22-23`: `if(!defined('OC_ADMIN')) { define('OC_ADMIN', false); }` — defaults to `false`, exactly what an importer (not the backoffice) wants |
| Default `$_SERVER` keys | `$_SERVER += ['HTTP_HOST'=>'localhost','REQUEST_URI'=>'/','REMOTE_ADDR'=>'127.0.0.1','SERVER_PORT'=>'80']` before `require_once oc-load.php` | `default-constants.php:47-48` reads `$_SERVER['HTTPS']`/`['SERVER_PORT']`/`['HTTP_HOST']` unguarded to build `WEB_PATH`; `oc-includes/osclass/utils.php`'s `osc_get_ip()` reads `$_SERVER['REMOTE_ADDR']`/`HTTP_CLIENT_IP`/`HTTP_X_FORWARDED_FOR` |
| Skip the page-dispatch switch | Not replicated — this CLI never routes a `page`/`action`, it calls the plugin's glue functions directly | `app/osclass/index.php:27-38` (the `Params::setParam('page', ...)` / upgrade-page block) and `:244-327` (the `switch(Params::getParam('page'))` controller dispatch) apply only to routing an HTTP-shaped request; a standalone operator script has no such request to route |
| Plugin loading | Not required directly — `Plugins::init()` inside `oc-load.php` loads the deployed plugin | `oc-load.php:326`: `Plugins::init();`, before which `tourist_directory_*` glue functions do not exist; `function_exists('tourist_directory_contact_email')` is the CLI's own "is it installed" signal |

## Design decisions taken while implementing (not pre-specified verbatim in design.md)

1. **`item_title` dual-use hazard (real vendor gotcha, not anticipated by design.md's prose).**
   `Plugins::$hooks` is one shared registry keyed by hook name for BOTH `runHook()` and
   `applyFilter()` (`app/osclass/oc-includes/osclass/classes/Plugins.php:25-76`). `item_title` is
   used as a void hook with zero args by the theme
   (`app/osclass/oc-content/themes/sigma/item.php:57`) AND as a value filter that MUTATES the title
   string during item save (`app/osclass/oc-includes/osclass/model/Item.php:1535,1649`) and
   display-time escaping (`app/osclass/oc-includes/osclass/controller/item.php:846`). A naive
   callback would have echoed badge markup into the title during every item save. Fixed by checking
   `func_get_args()`: zero args → theme's render call (safe to echo); one-or-more args → filter call
   (return the argument completely unchanged, no echo). Documented as an inline comment in
   `index.php` at `tourist_directory_item_title_hook()`.
2. **CSS enqueue hook: `header`, not literally `init`, despite tasks.md 2.7's wording.** `init`
   fires inside `BaseModel::__construct()` (`app/osclass/oc-includes/osclass/core/BaseModel.php:76`)
   before any HTML output context exists; echoing a `<link>` tag there would corrupt the response.
   `header` (`app/osclass/oc-content/themes/sigma/head.php:100`, inside `<head>`) is the correct,
   safe injection point and is the exact pattern Osclass's own `structured-data.php:117` uses for
   its footer hook. The front-end-only (`OC_ADMIN`) guard tasks.md asked for is preserved regardless
   of which hook carries it.
3. **Country/region/city geo placeholders for `_create`/`_update`.** `ItemActions::prepareData()`/
   `add()` unconditionally validates `countryName`/`regionName`/`cityName` with a minimum length of
   2 (`app/osclass/oc-includes/osclass/ItemActions.php` prepareData() country/region/city block,
   `add()` flash_error block ~line 156-165), even for an admin-created item. This site models
   destinations entirely through tourist-identity's category tree, not Osclass's native geo tables,
   so there is no real country/region/city data to supply. `tourist_directory_apply_geo_placeholders()`
   sets `country='Argentina'`, and `region`/`city` to the entry's own `localidad` when it is ≥2
   chars, else `'Argentina'` — always-valid, harmless placeholders for an unused taxonomy. This is a
   genuine implementation decision beyond design.md's prose (which does not mention geo fields at
   all).
4. **`_update`'s exact `ItemActions::edit()` return-value contract was not exhaustively read (U2).**
   **RESOLVED in U3** — see "U2's flagged `ItemActions::edit()` verification (resolved this batch)"
   below. `edit()` was read in full (`ItemActions.php:708-936`); the return contract is confirmed and
   a second, independent auto-link overwrite path inside `edit()` itself was found and neutralized by
   the same global pre-write guard.
5. **`_lookup_entry` returns `null` (fails closed) for `$itemId <= 0`**, not only on a DB
   error/exception — defense in depth, tightening rather than loosening the design's stated
   tri-state contract.
6. **`t_directory_entry` marker lookup for rendering (`tourist_directory_entry_row()`) filters
   `dt_retired IS NULL`**, so a retired entry immediately stops rendering the directory notice/badge
   even before its underlying item's own `b_active=0` fully propagates through caches — belt-and-
   suspenders with the guard, which (by design) still blocks a retired entry's contact/comment
   actions regardless of render state, since `tourist_directory_lookup_entry()` (the guard's lookup)
   matches on `fk_i_item_id` alone, ignoring `dt_retired`.

## Request-flow traces

All four traces below are grounded in the exact vendor call order read from `app/osclass/` this
session; none were executed against a live DB/site (no runtime harness available in this sandbox —
see Work Unit Evidence above).

### 1. Owner listing contact POST (sent, unaffected)

1. Visitor submits the item contact form → `page=item&action=contact_post`.
2. `CWebItem::__construct()` runs `osc_run_hook('init_item')`
   (`app/osclass/oc-includes/osclass/controller/item.php:39`) →
   `tourist_directory_init_item_guard()` reads `Params::getParam('action') === 'contact_post'` →
   matches the guarded-actions list → calls `tourist_directory_guard(null)`.
3. `tourist_directory_guard()` resolves `$id` from `Params::getParam('id')` (no row yet) →
   `tourist_directory_lookup_entry($id)` runs a fresh `SELECT pk_i_id FROM t_directory_entry WHERE
   fk_i_item_id = $id` → 0 rows (this is an owner item, no marker) → returns `false`.
   `tourist_directory_should_block(false, $contactEmail, $placeholder)` → `$contactEmail` (the
   item's real `s_contact_email`) does not equal the placeholder → returns `false`. No block; the
   request proceeds.
4. `doModel()`'s switch reaches `case 'contact_post'`
   (`app/osclass/oc-includes/osclass/controller/item.php:585`) → `osc_csrf_check()` (line 591) →
   `osc_run_hook('pre_item_contact_post', $item)` (line 628) → `tourist_directory_pre_post_guard($item)`
   → `tourist_directory_guard($item)` → `$rowId === $paramId`, lookup again returns `false`
   (fresh query, same result), `should_block` → `false` → no block.
5. `$mItem = new ItemActions(false); $mItem->contact();` runs normally and sends the email to the
   owner's real `s_contact_email` (`ItemActions.php` `contact()`/`prepareDataForFunction('contact')`,
   `app/osclass/oc-includes/osclass/ItemActions.php:1485-1500`). `osc_add_flash_ok_message("We've
   just sent an e-mail to the seller")` (line 638).

### 2. Entry contact POST with a valid CSRF token (blocked, nothing sent)

1. Attacker (or a scripted forged POST that first fetches and replays a valid CSRF token) submits
   `page=item&action=contact_post&id=<entry_id>` with a valid token.
2. `init_item` → `tourist_directory_init_item_guard()` → action `contact_post` matches → guard
   called with `null`. Lookup: `SELECT ... WHERE fk_i_item_id = <entry_id>` → 1 row → returns
   `true`. `should_block(true, ...)` → `true` (per `tourist_directory_should_block()`'s first branch:
   `if ($isEntry !== false) return true;`). Flash error set, `osc_redirect_to(osc_item_url_ns($id))`
   → **exits immediately** (`app/osclass/oc-includes/osclass/utils.php:2641`). `doModel()`,
   `osc_csrf_check()`, and `ItemActions::contact()` never run. No email is sent.
3. Even if step 2's early guard were somehow bypassed (it cannot be, since `osc_redirect_to()` calls
   PHP `exit`), the request would still reach `case 'contact_post'`
   (`app/osclass/oc-includes/osclass/controller/item.php:585`): `osc_csrf_check()` (line 591)
   validates the token (it is valid, so this passes), then `osc_run_hook('pre_item_contact_post',
   $item)` (line 628) fires `tourist_directory_pre_post_guard($item)` → guard runs again with the
   real item row → mismatch check passes (`$rowId === $paramId`) → fresh lookup → `true` again →
   blocks and exits before `$mItem->contact()` is ever constructed. Two independent, redundant block
   points; a valid CSRF token defeats neither.

### 3. Entry contact GET page (blocked, no form ever rendered)

1. Visitor requests `page=item&action=contact&id=<entry_id>`.
2. `init_item` → `tourist_directory_init_item_guard()` → action `contact` matches the guarded list
   → `tourist_directory_guard(null)` → lookup → `true` → block → flash error + `osc_redirect_to(...)`
   → exits.
3. `doModel()`'s `case 'contact':` (`app/osclass/oc-includes/osclass/controller/item.php:556`),
   which would otherwise call `$this->doView('item-contact.php')` (line 577), never runs. The
   contact form is never rendered for this item id.

### 4. Marker query failure (blocked, fails closed)

1. Any guarded action (`contact`, `contact_post`, `send_friend`, `send_friend_post`, `add_comment`)
   is requested for any item id while the DB connection is down, the `t_directory_entry` table is
   unexpectedly missing/locked, or the query throws.
2. `tourist_directory_lookup_entry($id)`'s `$dao->get()` returns `false` (query failure) →
   `tourist_directory_lookup_entry()` returns `null`; or an exception is thrown inside the `try`
   block → caught by `catch (\Throwable $e)` → returns `null` either way.
3. `tourist_directory_should_block(null, ...)` → `$isEntry !== false` is `true` (since `null !==
   false`) → returns `true` unconditionally, regardless of `$contactEmail`/`$placeholder` (that
   branch is never even reached). Block + redirect + exit. The request is rejected exactly as if a
   confirmed entry had been hit — never falls back to "assume owner item, let it through."

## Every Osclass API/hook used, with vendor `file:line`

| Symbol | Kind | Vendor location | Used for |
|---|---|---|---|
| `osc_run_hook('init_item')` | hook (void, 0 args) | `oc-includes/osclass/controller/item.php:39` | Early guard entry point |
| `Params::getParam('action')`/`('id')` set in `BaseModel::__construct()` | state | `oc-includes/osclass/core/BaseModel.php:66-67` | Confirms `action`/`id` are already resolved before `init_item` fires |
| `osc_run_hook('pre_item_contact_post', $item)` | hook (1 arg) | `oc-includes/osclass/controller/item.php:628` | Second guard point, contact |
| `osc_run_hook('pre_item_send_friend_post', $item)` | hook (1 arg) | `oc-includes/osclass/controller/item.php:541` | Second guard point, send-friend |
| `osc_run_hook('pre_item_add_comment_post', $item)` | hook (1 arg) | `oc-includes/osclass/controller/item.php:650` | Second guard point, comment |
| `osc_csrf_check()` ordering vs. the three hooks above | ordering | `item.php:591` (contact_post), `:521` (send_friend_post), `:645` (add_comment) — all strictly before their `pre_item_*_post` hook | Confirms CSRF is validated before the guard, so a forged POST must already carry a valid token |
| `osc_run_hook('show_item', $item)` | hook (1 arg) | `oc-includes/osclass/controller/item.php:862` | Registers per-page body-class + structured-data suppression filters |
| `osc_redirect_to($url, $code=null)` exits | behavior | `oc-includes/osclass/utils.php:2622-2641` (`exit;` at 2641) | Guarantees a block stops all further execution |
| `osc_item_url_ns($id, $locale='')` | helper | `oc-includes/osclass/helpers/hDefines.php:988-995` | Redirect target on block |
| `osc_contact_url()` | helper | `oc-includes/osclass/helpers/hDefines.php:591-598` | Base URL for the "Solicitar baja" removal link |
| `osc_run_hook('init_contact')` | hook (void) | `oc-includes/osclass/controller/contact.php:27` | Removal-link subject prefill |
| `ContactForm::the_subject()` reads `Session::_getForm('subject')` | consumer | `oc-includes/osclass/frm/Contact.form.class.php:92-100` | Confirms the prefill is actually read by the generic contact form |
| `osc_run_hook('item_title')` (void, 0 args) AND `osc_apply_filter('item_title', $title)` (filter, 1 arg) sharing one registry | dual-use hook/filter | theme void call: `oc-content/themes/sigma/item.php:57`; filter calls: `oc-includes/osclass/model/Item.php:1535,1649`, `oc-includes/osclass/controller/item.php:846`; shared registry: `oc-includes/osclass/classes/Plugins.php:25-76` (`addHook`/`runHook`/`applyFilter`) | The dual-use hazard documented in Design Decisions #1 |
| `osc_run_hook('item_sidebar_top')` | hook (void) | `oc-content/themes/sigma/item-sidebar.php:20` | Sidebar notice |
| `osc_run_hook('item_loop_title')` / `('item_loop_title', true)` | hook (void, ignores extra arg) | `oc-content/themes/sigma/loop-single.php:43`, `loop-single-premium.php:43` | Listing-card badge |
| `osc_run_hook("highlight_class")` | hook (void) | `oc-content/themes/sigma/loop-single.php:21`, `loop-single-premium.php:21` (also `oc-includes/osclass/gui/loop-single*.php:21`) | Card CSS class |
| `osc_apply_filter('sigma_bodyClass', array())` + `osc_add_filter('sigma_bodyClass', ...)` | filter (array) | `oc-content/themes/sigma/functions.php:141-158` (registration precedent), `:157-158` (application) | Entry-page body class |
| `osc_apply_filter('structured_data_show_footer_filter', true)` | filter (bool) | `oc-includes/osclass/structured-data.php:56` | Footer JSON-LD suppression |
| `osc_apply_filter('structured_data_show_header_filter', true)` | filter (bool) | `oc-includes/osclass/structured-data.php:121` | OG/Twitter tag suppression |
| `osc_item_price()` returns `null` when `i_price===''` | helper | `oc-includes/osclass/helpers/hItems.php:381-384` | Confirms an admin `-1` expiration and `price=''` really produce a NULL-price item |
| `osc_apply_filter('item_price_null', __('Check with seller'))` inside `osc_format_price()` | filter (string) | `oc-includes/osclass/helpers/hItems.php:1486-1488` | Price suppression for entries |
| `osc_item_id()` → `osc_item_field('pk_i_id')` → `osc_field(osc_item(), ...)` | helper chain | `oc-includes/osclass/helpers/hItems.php:154-156,205-207` | "Current item" context inside every rendering hook, both loop and single-item contexts |
| `osc_run_hook('header')` | hook (void) | `oc-content/themes/sigma/head.php:100` (also `oc-includes/osclass/gui/head.php:86`) | CSS `<link>` injection point (see Design Decision #2) |
| `osc_run_hook('init')` in `BaseModel::__construct()` | hook (void) | `oc-includes/osclass/core/BaseModel.php:76` | Confirms `init` fires too early for `<head>` output |
| `osc_plugins_url()` | helper | `oc-includes/osclass/helpers/hDefines.php:274-276` | Public CSS URL base |
| `Plugins::install($path)` — `install_<path>` hook, `ob_get_length()>0` fails the install | lifecycle | `oc-includes/osclass/classes/Plugins.php:389-429` (`ob_get_length` check at 422) | Confirms the install hook must never echo |
| `Plugins::uninstall($path)` — `deactivate($path)` then `<path>_uninstall` hook, table/pref never touched by the vendor itself | lifecycle | `oc-includes/osclass/classes/Plugins.php:436-470` (deactivate at 449, hook at 454) | Uninstall ordering |
| `Plugins::activate($path)` — `<path>_enable` hook | lifecycle | `oc-includes/osclass/classes/Plugins.php:477-499` (hook at 494) | Enable ordering; confirms a reinstall's automatic `activate()` reactivates non-retired entries |
| `Plugins::deactivate($path)` — `<path>_disable` hook | lifecycle | `oc-includes/osclass/classes/Plugins.php:506-535` (hook at 525) | Disable ordering |
| `Plugins::addHook()` strips the plugins-path prefix from the hook key | normalization | `oc-includes/osclass/classes/Plugins.php:722-744` | Confirms `TOURIST_DIRECTORY_PLUGIN . '_enable'` (a full path) normalizes to the same short-form key `Plugins::activate()`/`deactivate()` look up |
| `ItemActions::deactivate($id, $secret=null)` | model method | `oc-includes/osclass/ItemActions.php:1174-1198` | Disable/uninstall/retire |
| `ItemActions::activate($id, $secret=null)` | model method | `oc-includes/osclass/ItemActions.php:1029` | Enable/reactivate |
| `ItemActions::prepareData($is_add)` — `Params::getParam('price')===''` → `null`; admin `dt_expiration==-1` → `''`; admin `User::findByEmail(contactEmail)` auto-links a matching user | model method | `oc-includes/osclass/ItemActions.php:1778-1982` (price: 1859; expiration: 1942; findByEmail: 1785) | Confirms `_item_params`'s `price=''`/`dt_expiration='-1'` contract, and the auto-link risk the random placeholder avoids |
| `ItemActions::add()` — returns `$flash_error` string on failure, else int `1`/`2`; `Params::getParam('itemId')` set after insert | model method | `oc-includes/osclass/ItemActions.php:80-338` (return at 338, `itemId` param set at 254) | `_create`'s success/failure contract |
| `ItemActions::edit()` — structurally mirrors `add()`, tail `return $success`; also re-overwrites `contactName`/`contactEmail` from `$aItem['userId']` at 829-835, independent of `prepareData()`'s own overwrite | model method | `oc-includes/osclass/ItemActions.php:708-936` (return at 935; userId overwrite at 829-835) | `_update`'s contract, fully re-verified this batch — see "U2's flagged `ItemActions::edit()` verification" above |
| `Item::updateExpirationDate($id, $time)` — no-op, returns `false`, when `$time===''` | model method | `oc-includes/osclass/model/Item.php:1198-1201` | Confirms the admin `-1`→`''` expiration path leaves the DB default (`9999-12-31 23:59:59`) intact |
| `t_item` schema: `s_contact_email VARCHAR(140) NOT NULL`, `i_price BIGINT(20) NULL`, `dt_expiration DATETIME NOT NULL DEFAULT '9999-12-31 23:59:59'` | schema | `oc-includes/osclass/installer/struct.sql:247-283` | Guard's `s_contact_email` field name; price/expiration defaults |
| `osc_validate_email($email)` | helper | `oc-includes/osclass/helpers/hValidate.php:261-313` | Confirms `directorio-<hex>@directorio.invalid` passes Osclass's own email syntax validator |
| `DBCommandClass::select/from/where/get/insert/update/query`; `DBRecordsetClass::numRows/row/result`; `escape()` maps PHP `null`→SQL `NULL` literal, arrays→`''` | DB layer | `oc-includes/osclass/classes/database/DBCommandClass.php` (`select`216, `insert`708-729, `update`1030-1061, `where`/`_where`1119-1121,288-308, `get`1265-1280, `escape`340-352); `oc-includes/osclass/classes/database/DBRecordsetClass.php` (`result`101, `row`196, `numRows`347) | Marker table CRUD (`tourist_directory_lookup_entry`, `_entry_row`, `_marker_row`, `_insert_marker`, `_touch_marker`, `_retire`, `_reactivate`) |
| `osc_get_preference`/`osc_set_preference` | helper | (existing pattern already confirmed in `tourist-identity`; not re-read this session, matches `plugins/tourist-identity/index.php` usage) | `contact_email` pref read/write |
| `osc_get_locales_all('ALL', true)` | helper | (existing pattern already confirmed in `tourist-identity`; not re-read this session) | Installed-locale detection for `_item_params`'s `ctx['locales']` |
| `osc_esc_html`, `osc_esc_js` existence | helper | `oc-includes/osclass/helpers/hSanitize.php:180,215` (existence confirmed; only `osc_esc_html` used — no inline JS was needed) | Output escaping |
| `random_bytes()` (PHP 8.5 core) | language | n/a | Per-install placeholder email generation |

## Remaining Tasks (NOT started, out of this batch's scope)
- [ ] Phase 4: manual dry-run against the real Osclass/DB — task 4.1 (explicitly out of scope for
  this or any apply batch: requires the real server/DB and is gated on Phase 5's deployment)
- [ ] Phase 5: deployment [USER][AUTH REQUIRED] — tasks 5.1-5.6

## Workload / PR Boundary
- Mode: chained PR slice (auto-chain, stacked-to-main per tasks.md forecast)
- Current work unit: U3 — Import planner + CLI + README
- Boundary: starts from U2's clean, all-green baseline and ends with U3's full importer surface
  (`tourist_directory_plan()` + 3 private helpers, `tourist_directory_cli_parse_args()`,
  `tourist_directory_cli_should_refuse()`, `bin/tourist-directory-import.php`, and the README's
  "Importer CLI" section), all green and linted, tasks 3.1-3.6 checked off. U1+U2+U3 together
  complete every apply-phase task in tasks.md (Phases 1-3); only Phase 4 (manual dry-run,
  requires the real server) and Phase 5 (deployment, `[USER][AUTH REQUIRED]`) remain, and both are
  explicitly out of scope for an apply batch.
- Rollback boundary: see Work Unit Evidence above.
- Estimated review budget impact: ~699 authored changed lines for this slice, within the runtime
  ledger's 800-line cap for this work unit (no `size:exception` needed for U3, unlike U2), consistent
  with the already-resolved auto-chain/stacked-to-main decision.
