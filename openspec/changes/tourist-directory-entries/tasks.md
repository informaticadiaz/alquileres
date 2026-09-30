# Tasks: Tourist Directory Entries

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1100 total (U1 ~380, U2 ~400, U3 ~330) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | U1 -> U2 -> U3 |
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

## Phase 1: U1 — Showcase Dropdown + Directory Lib (Pure, TDD)

- [ ] 1.1 RED: extend `tests/test_tourist_showcase.php` — two new accommodation-type options and `tourist_showcase_merge_options` idempotence.
- [ ] 1.2 GREEN: add options to `tourist_showcase_definitions()`; implement `tourist_showcase_merge_options()`/`tourist_showcase_sync_options()` in `plugins/tourist-showcase/tourist-showcase-lib.php` and `plugins/tourist-showcase/tourist-showcase.php`; call sync from install.
- [ ] 1.3 RED: add tests for `tourist_directory_version`, `_seed_columns`, `_type_map`, `_parse_rows`, `_validate_row` (whitelist, dup id, bad destino/tipo/URL).
- [ ] 1.4 GREEN: create `plugins/tourist-directory/tourist-directory-lib.php` implementing those five functions.
- [ ] 1.5 RED: add tests for `_fingerprint`, `_description` (es_ES/en_US), `_locales`, `_text`, `_is_placeholder_email`, `_safe_url`, `_removal_url`, `_should_block` (null -> block).
- [ ] 1.6 GREEN: implement the eight functions above in `tourist-directory-lib.php`.
- [ ] 1.7 Verify U1: `php tests/test_tourist_showcase.php` green; `php -l` both lib files; commit.

## Phase 2: U2 — Plugin Install/Lifecycle/Guards/Rendering/CSS

- [ ] 2.1 RED: tests for `_item_params(entry, ctx)` (price NULL, placeholder `contactEmail`, admin owner fields).
- [ ] 2.2 GREEN: implement `_item_params` in `tourist-directory-lib.php`.
- [ ] 2.3 GREEN: create `plugins/tourist-directory/index.php` — install (`t_directory_entry` table, `tourist_directory.contact_email` pref, options sync), `_enable`/`_disable`/`_uninstall` lifecycle.
- [ ] 2.4 GREEN: implement `_lookup_entry(int)`/`_guard($item=null)`; wire to `init_item`, `pre_item_contact_post`, `pre_item_send_friend_post`, `pre_item_add_comment_post`.
- [ ] 2.5 GREEN: implement `_create`/`_update`/`_retire`/`_reactivate` (reactivate gated by `--allow-reactivate` in ctx; default: no auto-reactivation).
- [ ] 2.6 GREEN: implement render hooks (`item_title`, `item_sidebar_top`, `item_loop_title`, `highlight_class`, `show_item` with `sigma_bodyClass` filter + `structured_data_show_{footer,header}_filter=false`) and `tourist_directory_removal_url()`/`init_contact` prefill.
- [ ] 2.7 GREEN: create `plugins/tourist-directory/assets/tourist-directory.css` hiding contact/comment/price chrome; enqueue on `init`, front-end only.
- [ ] 2.8 GREEN: create `plugins/tourist-directory/README.md` (install, lifecycle, guard behavior, CSS scope).
- [ ] 2.9 Verify U2: `php tests/test_tourist_showcase.php` green; `php -l` on all new/changed PHP; note guard tests assume `osc_csrf_check()` runs before `pre_item_*_post` hooks; commit.

## Phase 3: U3 — Import Planner + CLI + README

- [ ] 3.1 RED: tests for `_plan(valid, existing, flags)` transitions (create/update/skip/retire `missing_item`/reactivate-gated).
- [ ] 3.2 GREEN: implement `_plan` in `tourist-directory-lib.php`.
- [ ] 3.3 GREEN: create `bin/tourist-directory-import.php` — CLI bootstrap (`ABS_PATH`, `CLI`, `OC_ADMIN` false, default `$_SERVER` keys); flags `--file=`, `--osclass-root=`, `--apply`, `--allow-placeholder-contact`, `--allow-reactivate`.
- [ ] 3.4 GREEN: wire production placeholder-contact gate and not-installed refusal; wire dry-run report and `--apply` write path via `Params`+`ItemActions(true)`+`_plan`; call `osc_update_cat_stats()`/`osc_cache_flush()` after apply.
- [ ] 3.5 GREEN: document CLI usage, flags, seed contract in `plugins/tourist-directory/README.md` and the CLI header comment.
- [ ] 3.6 Verify U3: `php tests/test_tourist_showcase.php` green; `php -l bin/tourist-directory-import.php`; commit.

## Phase 4: Dry-Run Only (Production Import Gated)

- [ ] 4.1 Manual dry-run: `php bin/tourist-directory-import.php --file=<seed> --osclass-root=app/osclass` (no `--apply`) against a sample of real seed rows; record output. `--apply` stays blocked until site mail works and `osclass.contactEmail` is real — that is a separate future task, not part of this change's completion criteria.

## Phase 5: Deployment [USER][AUTH REQUIRED]

- [ ] 5.1 Agent: pre-deploy `mysqldump` backup into `data/osclass/backups/`.
- [ ] 5.2 [USER] Sync `plugins/tourist-showcase/{tourist-showcase.php,tourist-showcase-lib.php}` flat into `app/osclass/oc-content/plugins/` (read-only) FIRST.
- [ ] 5.3 [USER] Sync `plugins/tourist-directory/` into `app/osclass/oc-content/plugins/tourist-directory/` (read-only) via `diff -r`.
- [ ] 5.4 [USER][AUTH REQUIRED] Install `tourist-directory` from oc-admin.
- [ ] 5.5 Agent: read-only HTTPS verification — render, no price, guards, dropdown values.
- [ ] 5.6 [USER][AUTH REQUIRED] Optional smoke test: create one test entry, forge CSRF-valid POSTs over HTTPS to confirm guard rejection, then deactivate the test entry.
