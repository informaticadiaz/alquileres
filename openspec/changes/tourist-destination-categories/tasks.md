# Tasks: Tourist Destination Categories

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~630 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 tree data → PR2 pure plans → PR3 wiring+README |
| Delivery strategy | auto-chain |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | PR | Focused test | Runtime harness | Rollback |
|---|---|---|---|---|---|
| 1 | Tree data + shape tests | 1 | `php tests/test_tourist_showcase.php` | N/A, pure data | Delete `tourist-identity-tree.php` + its tests |
| 2 | Pure plan/restore/message fns | 2 | same, plan/uninstall/slug/message assertions | N/A, pure fns, no DB | Revert lib additions; Unit 1 unaffected |
| 3 | Wire `index.php` + README + spec | 3 | same, full regression | User: Configure → Re-apply ×2, inline count | Uninstall restores snapshot; or redeploy 1.0.0 from backup |

## Phase 1: Tree Data (Unit 1, PR 1)

- [x] 1.1 RED: test `tourist_identity_tree()` = 6 regions in order (Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia).
- [x] 1.2 RED: test 51 leaves, `research.md` order per region, catch-all last, unique `^[a-z0-9-]+$` keys, both locale names present.
- [x] 1.3 GREEN: create `plugins/tourist-identity/tourist-identity-tree.php` with `tourist_identity_tree()`; anchor 47 = Buenos Aires.
- [x] 1.4 Require the new file from `tests/test_tourist_showcase.php`.
- [x] 1.5 VERIFY: test green, `php -l` on the new file; commit Unit 1. (test + lint green; commit intentionally deferred — orchestrator scope for this apply batch excludes commit/push)

## Phase 2: Pure Plans (Unit 2, PR 2)

Files: `plugins/tourist-identity/tourist-identity-lib.php` unless noted.

- [ ] 2.1 RED: `tourist_identity_tree_plan()` — empty map inserts regions then leaves; anchor 47 gets describe/update only; full-map rerun is empty (idempotent); map id missing from rows is re-inserted.
- [ ] 2.2 RED: `tourist_identity_leaf_ids()` returns the 51 ids in tree order from a full map.
- [ ] 2.3 RED: `tourist_identity_showcase_link_needed()` set-compares an array target (51 ids, any order); prior scalar behavior kept.
- [ ] 2.4 RED: `tourist_identity_resolve_slug()` — base slug when free, `-2`/`-3` on collision, self-exclusion via injected `ownerOf`/`selfId`.
- [ ] 2.5 RED: `tourist_identity_build_tree_snapshot()`/`tourist_identity_tree_restore_plan()` round-trip; invalid JSON throws.
- [ ] 2.6 RED: `tourist_identity_uninstall_plan()` — zero-item row deletes, item-holding row disables (kept in map), leaves before region, `null` count disables.
- [ ] 2.7 RED: `tourist_identity_prune_ids()` removes given ids, keeps order/uniqueness.
- [ ] 2.8 RED: `tourist_identity_reapply_message(0)` === `'Re-apply complete: 0 change(s).'`; `tourist_identity_version()` === `'1.1.0'`.
- [ ] 2.9 GREEN: implement `tourist_identity_tree_plan()` (delegates others to `tourist_identity_category_plan`), `tourist_identity_leaf_ids()`, `tourist_identity_resolve_slug()`, `tourist_identity_build_tree_snapshot()`, `tourist_identity_tree_restore_plan()`.
- [ ] 2.10 GREEN: implement `tourist_identity_uninstall_plan()`, `tourist_identity_prune_ids()`, `tourist_identity_reapply_message()`; update `tourist_identity_showcase_link_needed()` for array targets; bump `tourist_identity_version()` to `1.1.0`.
- [ ] 2.11 VERIFY: test green (prior assertions kept), `php -l`; commit Unit 2.

## Phase 3: Wiring + Docs (Unit 3, PR 3)

Files: `plugins/tourist-identity/index.php` unless noted.

- [ ] 3.1 GREEN: add `_read_descriptions($ids)`, `_item_count($id)` (DAO `COUNT(*)`, `false`⇒`null`), `_ensure_tree_snapshot()` (writes `snapshot_tree` only if absent).
- [ ] 3.2 GREEN: add `_apply_tree()`: `Category::insert()` with explicit `i_position` (regions then leaves, save `category_map` per insert), DAO updates, description writes via `tourist_identity_resolve_slug()`.
- [ ] 3.3 GREEN: update `tourist_identity_apply()` to call `_ensure_tree_snapshot()` + `_apply_tree()`; link all 51 leaf ids via the showcase-link step.
- [ ] 3.4 GREEN: add `_restore_tree()`: restore 47 from `snapshot_tree`, run the uninstall plan (vendor `deleteByPrimaryKey()` only when own item count is 0, no surviving children, leaves before regions), save the reduced map, prune `tourist_showcase.category_ids`.
- [ ] 3.5 GREEN: call `_restore_tree()` from `tourist_identity_uninstall()`; bump plugin header to `1.1.0`.
- [ ] 3.6 GREEN: update `tourist_identity_configure()` to persist and render the last re-apply count inline, independent of the flash message.
- [ ] 3.7 DOCS: update `plugins/tourist-identity/README.md` — tree, `snapshot_tree`/`category_map` prefs, Re-apply upgrade (no version hook), rollback.
- [ ] 3.8 SPEC: add a scenario under "Restore on Uninstall" in `openspec/changes/tourist-destination-categories/specs/identity-setup-lifecycle/spec.md`: prune `tourist_showcase.category_ids` of ids deleted on uninstall.
- [ ] 3.9 VERIFY: test green, `php -l` on the 3 touched files; commit Unit 3.

## Phase 4: Deployment [USER] [AUTH REQUIRED]

- [ ] 4.1 [USER] Backup: `mysqldump --single-transaction --no-tablespaces <db> > data/osclass/backups/pre-destination-categories-<date>.sql` (read-only).
- [ ] 4.2 [USER] Sync: `cp -a plugins/tourist-identity/. app/osclass/oc-content/plugins/tourist-identity/` (read-only), then `diff -r plugins/tourist-identity/ app/osclass/oc-content/plugins/tourist-identity/` (read-only) confirms match.
- [ ] 4.3 [USER][AUTH REQUIRED] Configure → "Re-apply" once; inline count > 0.
- [ ] 4.4 [USER][AUTH REQUIRED] Configure → "Re-apply" again; inline result reads "0 change(s)".
- [ ] 4.5 Agent, read-only HTTPS `curl`: home lists 6 regions in order; a region page lists leaves, catch-all last; publish form offers only leaves; showcase fields on a leaf; en_US checked.
