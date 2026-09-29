# Tasks: Tourist Portal Identity

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~650 (lib ~220, tests +140, index.php ~200, logo ~30, README ~80) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (lib+tests) → PR 2 (plugin wiring+assets+README) → gated deployment (not a PR) |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|---|---|---|---|---|---|
| 1 | Lib contracts, RED→GREEN | PR 1 | `php tests/test_tourist_showcase.php` | N/A, pure functions | Revert lib file + its test assertions |
| 2 | Plugin wiring | PR 2 | `php -l plugins/tourist-identity/index.php` + test run | N/A, verified only in gated deploy (4.x) | Delete `index.php`, `assets/`, `README.md`; lib untouched |
| 3 | Deployment (gated) | N/A, ops task | Task 4.4 HTTPS checklist | Live install+re-apply on production | Uninstall restores snapshot; item deletion irreversible, accepted |

## Phase 1: Pure Lib Contracts (RED → GREEN) — PR 1

- [ ] 1.1 RED: add failing assertions in `tests/test_tourist_showcase.php` for `tourist_identity_version()`, `tourist_identity_target_prefs()`, `tourist_identity_override_map()`, `tourist_identity_override_text()` (incl. `OC_ADMIN` guard).
- [ ] 1.2 RED: add assertions for `tourist_identity_category_plan()` (changed-rows-only, empty on 2nd pass) and currency helpers.
- [ ] 1.3 RED: add assertions for `tourist_identity_build_snapshot()` (null-vs-empty pref) and `tourist_identity_restore_plan()` (round-trip, invalid-JSON throw).
- [ ] 1.4 RED: add assertions for `tourist_identity_disclaimer()` (es_ES/en_US) and `tourist_identity_configure_url()`.
- [ ] 1.5 GREEN: create `plugins/tourist-identity/tourist-identity-lib.php` implementing all contracts (design §Interfaces/Contracts) until tests pass.
- [ ] 1.6 Lint `plugins/tourist-identity/tourist-identity-lib.php` with `php -l`.

## Phase 2: Osclass-Bound Plugin Wiring — PR 2

- [ ] 2.1 Create `plugins/tourist-identity/index.php`: header + hooks (`install_`, `_uninstall`, `_configure`, `gettext`, `footer`, `init`).
- [ ] 2.2 `tourist_identity_install()`: snapshot into pref section `tourist_identity` before any write (spec: Snapshot on Install).
- [ ] 2.3 `_apply()`: category DAO update + `osc_update_cat_stats()`, ARS insert/flags/default, core+sigma prefs, logo copy, showcase-47 link if `function_exists`, delete items 1/2 via `ItemActions`, return change count.
- [ ] 2.4 `_uninstall()`: restore snapshot, keep ARS disabled, delete plugin logo + `tourist_identity` section (spec: Restore on Uninstall).
- [ ] 2.5 `_configure()`: CSRF-checked "Re-apply" action calling `_apply()`.
- [ ] 2.6 `_gettext($s)`: apply override map, guarded by `OC_ADMIN === false` (spec: Public-Only Gettext Overrides).
- [ ] 2.7 `_footer()` disclaimer + `_init_currencies()` exporting enabled-only `currencies` View.
- [ ] 2.8 Add `plugins/tourist-identity/assets/logo.svg`.
- [ ] 2.9 Add `plugins/tourist-identity/README.md`: ops notes, source-string list, re-sync reminder.
- [ ] 2.10 Lint `index.php`; re-run `php tests/test_tourist_showcase.php` (no regression).

## Phase 3: Local Verification

- [ ] 3.1 Re-run `php tests/test_tourist_showcase.php`; all assertions pass.
- [ ] 3.2 Trace `_apply()` idempotency by hand: second-call plan is empty against applied state.

## Phase 4: Deployment (Gated — requires explicit user authorization; DB-changing, public site)

- [ ] 4.1 [AUTH REQUIRED] Copy `plugins/tourist-identity/` into `app/osclass/oc-content/plugins/tourist-identity/`; verify `diff -r plugins/tourist-identity/ app/osclass/oc-content/plugins/tourist-identity/`.
- [ ] 4.2 [AUTH REQUIRED] Install plugin from oc-admin Plugins page (DB write, deletes items 1/2).
- [ ] 4.3 [AUTH REQUIRED] Trigger "Re-apply" once; confirm 0 changes.
- [ ] 4.4 [AUTH REQUIRED] Verify over HTTPS: title, meta desc, H1, CTA, placeholder, logo, disclaimer, no "Powered by Osclass", single category, former parent 4 empty, ARS-only form, items 1/2 → 404, en_US locale, admin unaffected.
