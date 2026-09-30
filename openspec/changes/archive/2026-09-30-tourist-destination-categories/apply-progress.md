# Apply Progress: Tourist Destination Categories

## Status

Unit 1 (Phase 1: Tree Data, PR 1) — **complete**. Unit 2 (Phase 2: Pure Plans, PR 2) — **complete**. Unit 3 (Phase 3: Wiring + Docs, PR 3) — **complete**. Unit 4 (Phase 4: Deployment, [USER]/[AUTH REQUIRED]) — **complete** (2026-09-30): backup `data/osclass/backups/pre-tourist-destinations-20260929T224115.sql` taken by the orchestrator; sync, `diff -r` and Re-apply ×2 performed by the user (second Re-apply: 0 changes, `last_reapply_changes = 0`); read-only DB and HTTPS verification by the orchestrator (commit `7484701`).

## Completed Tasks

### Phase 1 (Unit 1, PR 1)

- [x] 1.1 RED: test `tourist_identity_tree()` = 6 regions in order (Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia).
- [x] 1.2 RED: test 51 leaves, `research.md` order per region, catch-all last, unique `^[a-z0-9-]+$` keys, both locale names present.
- [x] 1.3 GREEN: create `plugins/tourist-identity/tourist-identity-tree.php` with `tourist_identity_tree()`; anchor 47 = Buenos Aires.
- [x] 1.4 Require the new file from `tests/test_tourist_showcase.php`.
- [x] 1.5 VERIFY: test green, `php -l` on the new file. Commit intentionally **deferred** — apply batch scope excludes commit/push.

### Phase 2 (Unit 2, PR 2)

- [x] 2.1 RED: `tourist_identity_tree_plan()` — empty map inserts regions then leaves; anchor 47 gets describe/update only; full-map rerun is empty (idempotent); map id missing from rows is re-inserted.
- [x] 2.2 RED: `tourist_identity_leaf_ids()` returns the 51 ids in tree order from a full map.
- [x] 2.3 RED: `tourist_identity_showcase_link_needed()` set-compares an array target (51 ids, any order); prior scalar behavior kept.
- [x] 2.4 RED: `tourist_identity_resolve_slug()` — base slug when free, `-2`/`-3` on collision, self-exclusion via injected `ownerOf`/`selfId`.
- [x] 2.5 RED: `tourist_identity_build_tree_snapshot()`/`tourist_identity_tree_restore_plan()` round-trip; invalid JSON throws.
- [x] 2.6 RED: `tourist_identity_uninstall_plan()` — zero-item row deletes, item-holding row disables (kept in map), leaves before region, `null` count disables.
- [x] 2.7 RED: `tourist_identity_prune_ids()` removes given ids, keeps order/uniqueness.
- [x] 2.8 RED: `tourist_identity_reapply_message(0)` === `'Re-apply complete: 0 change(s).'`; `tourist_identity_version()` === `'1.1.0'`.
- [x] 2.9 GREEN: implemented `tourist_identity_tree_plan()` (with private helper `tourist_identity_tree_plan_row()`), `tourist_identity_leaf_ids()`, `tourist_identity_resolve_slug()`, `tourist_identity_build_tree_snapshot()`, `tourist_identity_tree_restore_plan()`.
- [x] 2.10 GREEN: implemented `tourist_identity_uninstall_plan()`, `tourist_identity_prune_ids()`, `tourist_identity_reapply_message()`; updated `tourist_identity_showcase_link_needed()` for array targets (scalar contract kept); bumped `tourist_identity_version()` to `1.1.0`.
- [x] 2.11 VERIFY: test green (all prior + new assertions), `php -l` on all 3 touched files. Commit intentionally **deferred** — apply batch scope excludes commit/push.

### Phase 3 (Unit 3, PR 3)

- [x] 3.1 GREEN: added `tourist_identity_read_descriptions($ids)`, `tourist_identity_item_count($id)` (dedicated `COUNT(*)` on `t_item`, `false`⇒`null`), `tourist_identity_ensure_tree_snapshot()` (writes `snapshot_tree` only if absent). Also added two new pure lib functions this task needed (RED→GREEN, see TDD table): `tourist_identity_tree_locales()`, `tourist_identity_rows_excluding_ids()`.
- [x] 3.2 GREEN: added `tourist_identity_apply_tree()`: `Category::insert()` with explicit `i_position`/`fk_i_parent_id` resolved from the created-id map (regions before their leaves), `category_map` pref saved immediately after each insert, DAO-level updates for existing rows, description writes via `tourist_identity_write_description()` + `tourist_identity_resolve_slug()`.
- [x] 3.3 GREEN: `tourist_identity_apply()` now calls `tourist_identity_ensure_tree_snapshot()` first (before any category write), then `tourist_identity_apply_tree()`; `tourist_identity_apply_showcase_link()` now links all resolved leaf ids (`tourist_identity_leaf_ids()`) instead of the single legacy category.
- [x] 3.4 GREEN: added `tourist_identity_restore_tree()`: restores 47's position/descriptions from `snapshot_tree`, runs `tourist_identity_uninstall_plan()` with `[47]` as the protected id (vendor `deleteByPrimaryKey()` only for plan-listed ids, leaves before regions), saves the reduced map (or deletes the pref if empty), prunes `tourist_showcase.category_ids` of every deleted id.
- [x] 3.5 GREEN: `tourist_identity_uninstall()` now calls `tourist_identity_restore_tree()`; plugin header bumped to `1.1.0` (also deletes the new `last_reapply_changes` pref on uninstall).
- [x] 3.6 GREEN: `tourist_identity_configure()` now persists `last_reapply_changes` on every `tourist_identity_apply()` run and renders it inline (via `tourist_identity_reapply_message()`, escaped) independent of the flash message.
- [x] 3.7 DOCS: `plugins/tourist-identity/README.md` updated — destination tree explanation, `snapshot_tree`/`category_map` prefs, upgrade-from-1.0.0 procedure (no version hook, Re-apply twice), uninstall semantics (delete-if-empty/disable-if-in-use, showcase pruning, snapshot_tree restore), updated verification checklist.
- [x] 3.8 SPEC: added "Showcase linkage is pruned of deleted categories" scenario under "Restore on Uninstall" in `specs/identity-setup-lifecycle/spec.md`, plus one clause in that requirement's MUST statement so the scenario isn't orphaned from the requirement text.
- [x] 3.9 VERIFY: `php tests/test_tourist_showcase.php` green (106/106 assertions, exit 0); `php -l` clean on `index.php`, `tourist-identity-lib.php`, `tourist-identity-tree.php`, and `tests/test_tourist_showcase.php`. Commit intentionally **deferred** — this apply batch's scope explicitly excludes commit/push.

## Remaining Tasks (out of scope for this batch, untouched)

- [x] Phase 4 (Deployment, [USER]/[AUTH REQUIRED]): 4.1–4.5 — done 2026-09-30

## Files Changed

### Unit 1 (prior batch)

| File | Action | Lines | What Was Done |
|------|--------|-------|----------------|
| `plugins/tourist-identity/tourist-identity-tree.php` | Created | 112 | Pure data file: `tourist_identity_tree()` returns 6 regions in confirmed order; Buenos Aires carries `anchor => 47`; each region has `names['es_ES'\|'en_US']` and a `leaves` array of `[key, es_name, en_name]` triples, catch-all last. |
| `tests/test_tourist_showcase.php` | Modified | +102 | Added `require` for the new tree file; added RED-then-GREEN shape assertions for the tree. |
| `openspec/changes/tourist-destination-categories/tasks.md` | Modified | 5 checkboxes | Marked 1.1–1.5 `[x]`. |

### Unit 2 (this batch)

| File | Action | Lines | What Was Done |
|------|--------|-------|----------------|
| `plugins/tourist-identity/tourist-identity-lib.php` | Modified | +275 / -5 | Added 8 new pure functions: `tourist_identity_tree_plan()` (+ private helper `tourist_identity_tree_plan_row()`), `tourist_identity_leaf_ids()`, `tourist_identity_resolve_slug()`, `tourist_identity_build_tree_snapshot()`, `tourist_identity_tree_restore_plan()`, `tourist_identity_uninstall_plan()`, `tourist_identity_prune_ids()`, `tourist_identity_reapply_message()`. Updated `tourist_identity_showcase_link_needed()` to set-compare against an array target while keeping the scalar contract. Bumped `tourist_identity_version()` to `1.1.0`. |
| `tests/test_tourist_showcase.php` | Modified | +278 | Added 8 RED-then-GREEN assertion blocks (Phase 2.1–2.8), each triangulated with 2–5 cases covering happy path + edge/branching paths (idempotency, map-drift reinsert, self-exclusion, leaves-before-regions, null-count, dedup). |
| `openspec/changes/tourist-destination-categories/tasks.md` | Modified | 11 checkboxes | Marked 2.1–2.11 `[x]`. |

### Unit 3 (this batch)

| File | Action | Lines | What Was Done |
|------|--------|-------|----------------|
| `plugins/tourist-identity/tourist-identity-lib.php` | Modified | +24 / -0 | Added 2 new pure functions (RED→GREEN, see TDD table): `tourist_identity_tree_locales(array $installedCodes)` (intersects the fixed `['es_ES','en_US']` set with what is actually installed, preserving order), `tourist_identity_rows_excluding_ids(array $rows, array $excludedIds)` (filters a category-rows list by excluded `pk_i_id`, preserving order). |
| `plugins/tourist-identity/index.php` | Modified | +324 / -7 | Osclass-bound wiring only (no new decision logic): `tourist_identity_read_category_map()`/`tourist_identity_save_category_map()` (JSON pref helpers); `tourist_identity_apply_categories()` rewritten to exclude tree-managed ids (anchor + every mapped id) before calling the existing `tourist_identity_category_plan($others, 0)`, so re-applying never re-disables the tree's own rows; `tourist_identity_read_descriptions()`, `tourist_identity_item_count()` (dedicated `COUNT(*)` on `t_item`), `tourist_identity_ensure_tree_snapshot()`, `tourist_identity_installed_tree_locales()`, `tourist_identity_write_description()` (update-else-insert on `t_category_description`), `tourist_identity_owner_of_slug()` (slug-collision lookup via `Category::findBySlug()`), `tourist_identity_apply_tree()` (executes `tourist_identity_tree_plan()`'s insert/update/describe entries against the DB); `tourist_identity_apply_showcase_link()` now links the 51 resolved leaf ids instead of the single legacy category; `tourist_identity_apply()` now calls `tourist_identity_ensure_tree_snapshot()` first and `tourist_identity_apply_tree()`, and persists `last_reapply_changes`; `tourist_identity_restore_tree()` (executes `tourist_identity_uninstall_plan()`'s delete/disable, restores 47 from `snapshot_tree`, prunes `tourist_showcase.category_ids`); `tourist_identity_uninstall()` now calls it and deletes `last_reapply_changes`; `tourist_identity_configure()` now renders the persisted last re-apply count inline; plugin header description/version bumped to `1.1.0`; requires the new tree-data file. |
| `tests/test_tourist_showcase.php` | Modified | +38 | Added RED-then-GREEN assertions (3 cases) for `tourist_identity_tree_locales()` and (2 cases) for `tourist_identity_rows_excluding_ids()`. |
| `plugins/tourist-identity/README.md` | Modified | +99 / -24 | Rewrote for the destination tree: new "Árbol de destinos" section, upgrade-from-1.0.0 procedure (no version hook; Re-apply twice), rewritten "Desinstalación" (delete-if-empty/disable-if-in-use, showcase-id pruning, `snapshot_tree` restore), updated "Qué cambia" table and verification checklist (6 regions/51 leaves, showcase fields on a leaf, 404/410 for deleted items). |
| `openspec/changes/tourist-destination-categories/specs/identity-setup-lifecycle/spec.md` | Modified | +8 / -1 | Added one clause to the "Restore on Uninstall" MUST statement and a new "Showcase linkage is pruned of deleted categories" scenario. |
| `openspec/changes/tourist-destination-categories/tasks.md` | Modified | 9 checkboxes | Marked 3.1–3.9 `[x]`. |

## TDD Cycle Evidence (Unit 2)

| Task | Function(s) | Test File | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-------------|-----------|------------|-----|-------|-------------|----------|
| 2.1 / 2.9 (partial) | `tourist_identity_tree_plan()`, `tourist_identity_tree_plan_row()` | `tests/test_tourist_showcase.php` | ✅ baseline green before edit | ✅ `Call to undefined function tourist_identity_tree_plan()` (exit 255) | ✅ `Tourist showcase checks passed.` (exit 0) after implementation | ✅ 3 cases: empty-map insert (regions-then-leaves, anchor excluded), fully-applied idempotent rerun (empty plan), map-drift re-insert | ➖ None needed — helper already extracted to avoid duplication between anchor/region/leaf branches |
| 2.2 / 2.9 (partial) | `tourist_identity_leaf_ids()` | same | ✅ (continuation of green run) | ✅ `Call to undefined function tourist_identity_leaf_ids()` (exit 255) | ✅ Passed after implementation | ✅ 2 cases: full 51-id map in tree order, partial map (skips unmapped leaf) | ➖ None needed |
| 2.3 / 2.10 (partial) | `tourist_identity_showcase_link_needed()` (array-target support) | same | ✅ | ✅ **Genuine assertion-failure RED** — `FAIL: an array target matches selected ids regardless of order` (exit 1) against the pre-existing scalar-only implementation | ✅ Passed after set-compare rewrite; all 5 pre-existing scalar assertions (lines 263–267) still pass | ✅ 3 cases: array target any-order match, array target mismatch, scalar target preserved | ➖ None needed |
| 2.4 / 2.9 (partial) | `tourist_identity_resolve_slug()` | same | ✅ | ✅ `Call to undefined function tourist_identity_resolve_slug()` (exit 255) | ✅ Passed after implementation | ✅ 4 cases: free base slug, single collision (`-2`), double collision (`-3`), self-exclusion (owner === selfId returns base slug) | ➖ None needed |
| 2.5 / 2.9 (partial) | `tourist_identity_build_tree_snapshot()`, `tourist_identity_tree_restore_plan()` | same | ✅ | ✅ `Call to undefined function tourist_identity_build_tree_snapshot()` (exit 255) | ✅ Passed after implementation | ✅ round-trip (id/i_position/descriptions) + invalid-JSON throw | ➖ None needed |
| 2.6 / 2.10 (partial) | `tourist_identity_uninstall_plan()` | same | ✅ | ✅ `Call to undefined function tourist_identity_uninstall_plan()` (exit 255) | ✅ Passed after implementation | ✅ 5 cases: zero-count delete, in-use disable+kept-in-map, null-count disable, leaf-before-region full delete, region-blocked-by-surviving-leaf disable | ➖ None needed |
| 2.7 / 2.10 (partial) | `tourist_identity_prune_ids()` | same | ✅ | ✅ `Call to undefined function tourist_identity_prune_ids()` (exit 255) | ✅ Passed after implementation | ✅ 3 cases: removal preserves order, dedup with nothing removed, remove-all-yields-empty | ➖ None needed |
| 2.8 / 2.10 (partial) | `tourist_identity_reapply_message()`, `tourist_identity_version()` bump | same | ✅ | ✅ `Call to undefined function tourist_identity_reapply_message()` (exit 255) | ✅ Passed after implementation | ✅ 2 cases: zero changes, non-zero changes; version bump asserted directly | ➖ None needed |

### Process note (self-reported deviation, corrected)

While implementing 2.1/2.9, the array-target rewrite of `tourist_identity_showcase_link_needed()` (task 2.10's scope) was drafted in the same edit before its RED test (2.3) existed — a Law-1 violation ("do NOT write production code until you have a failing test"). This was caught before running the suite: the change was **reverted**, the RED test for 2.3 was written and run to confirm a genuine assertion-failure RED against the original scalar-only implementation, and only then was the array-target rewrite re-applied and confirmed GREEN. The evidence row for 2.3 above reflects the corrected, genuine RED→GREEN cycle. No other task had this issue.

### Test Summary (Unit 2)

- **Total tests written**: 39 new `expect_true`/`expect_throws` assertions across 8 blocks (Phase 2.1–2.8).
- **Total tests passing**: all (39 new + 62 pre-existing = 101 assertions in one exit-0 run).
- **Layers used**: Unit (39) — all pure functions, no DB/HTTP boundary.
- **Approval tests** (refactoring): None — no refactoring tasks in this unit; `tourist_identity_showcase_link_needed()`'s 5 pre-existing scalar assertions served as the safety net proving backward compatibility.
- **Pure functions created**: 8 new (`tourist_identity_tree_plan`, `tourist_identity_leaf_ids`, `tourist_identity_resolve_slug`, `tourist_identity_build_tree_snapshot`, `tourist_identity_tree_restore_plan`, `tourist_identity_uninstall_plan`, `tourist_identity_prune_ids`, `tourist_identity_reapply_message`) + 1 private helper (`tourist_identity_tree_plan_row`), all zero side effects, standalone-loadable.

### RED evidence samples

```
$ php tests/test_tourist_showcase.php
PHP Fatal error:  Uncaught Error: Call to undefined function tourist_identity_tree_plan() in
/home/ignacio/fewohbee/tests/test_tourist_showcase.php:386
EXIT: 255
```

```
$ php tests/test_tourist_showcase.php
FAIL: an array target matches selected ids regardless of order
EXIT: 1
```
(genuine behavioral RED against the pre-existing scalar-only `tourist_identity_showcase_link_needed()`, not an undefined-function error)

### Final GREEN evidence

```
$ php tests/test_tourist_showcase.php
Tourist showcase checks passed.
EXIT: 0
```

### Lint evidence

```
$ php -l plugins/tourist-identity/tourist-identity-lib.php
No syntax errors detected in plugins/tourist-identity/tourist-identity-lib.php
$ php -l plugins/tourist-identity/tourist-identity-tree.php
No syntax errors detected in plugins/tourist-identity/tourist-identity-tree.php
$ php -l tests/test_tourist_showcase.php
No syntax errors detected in tests/test_tourist_showcase.php
```

## Work Unit Evidence (Unit 2)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `php tests/test_tourist_showcase.php` → 8 individual RED confirmations (7 undefined-function fatals, 1 genuine assertion-failure), each followed by a GREEN run (`Tourist showcase checks passed.`, exit 0); final full run: 101/101 assertions passing, exit 0 |
| Runtime harness command/scenario and exact result | N/A — pure functions, no DB/HTTP boundary in this unit (per tasks.md Suggested Work Units table); no Osclass bootstrap involved |
| Rollback boundary | Revert the 8 new functions + the `tourist_identity_showcase_link_needed()` rewrite + the `tourist_identity_version()` bump in `tourist-identity-lib.php`, and revert the Phase 2.1–2.8 assertion blocks in `tests/test_tourist_showcase.php`. `index.php`, `README.md`, `app/`, `data/`, and Unit 1's `tourist-identity-tree.php` are untouched and unaffected; Unit 1 remains independently valid. |

## Deviations from Design

None in the final implemented shape. One process deviation occurred and was self-corrected during implementation (see "Process note" above under Unit 2's TDD Cycle Evidence) — the final code and test sequence reflect a fully corrected RED→GREEN cycle for every function.

Two design choices needed concretization beyond design.md's abstract interface (design.md did not specify these at the pure-function level, only at the data-flow level):
- `tourist_identity_tree_plan()`'s `insert` entries carry `fields => ['b_enabled' => 1, 'fk_i_parent_id' => null]` as placeholder DB flags; the actual `fk_i_parent_id` for a leaf insert is intentionally left for Unit 3's `index.php` to resolve via `parent_key` + the id map, since a leaf's parent id may not exist yet at plan time (parent inserted in the same apply pass).
- `tourist_identity_uninstall_plan()` classifies "leaf vs. region" dynamically from `$rows`' `fk_i_parent_id` adjacency (any id with no children present in `$rows` is treated as a leaf), rather than requiring the caller to pre-classify — this keeps the function ignorant of the specific tree shape, matching its pure/generic contract.

## Issues Found

None. Baseline before this batch was 62 assertions (45 original + 17 from Unit 1). Unit 2 added 39 more, for a total of 101 assertions, all green in one run.

## Workload / PR Boundary

- Mode: chained PR slice (`stacked-to-main`, per tasks.md Review Workload Forecast, `delivery_strategy: auto-chain`)
- Current work unit: Unit 2 — Pure plan/restore/message functions (PR 2)
- Boundary: starts from Unit 1's clean baseline (62 assertions green, `tourist-identity-tree.php` untouched) and ends with 8 new pure functions + 1 helper in `tourist-identity-lib.php`, their 39 triangulated assertions, and the array-target generalization of `tourist_identity_showcase_link_needed()` — all green. Does not touch `index.php`, `README.md`, `app/`, `data/`, specs, or the live site.
- Estimated review budget impact: authored diff is **275 insertions + 5 deletions in `tourist-identity-lib.php`, and 278 insertions in `tests/test_tourist_showcase.php`** = **553 authored changed lines** (checkbox-only `tasks.md` edits excluded). This **exceeds the 400-line PR budget** for this single slice.
  - **Why it was not split further**: the 8 functions are one cohesive, interdependent pure-function unit — `tourist_identity_tree_plan()` alone requires the full 6-region/51-leaf tree fixture to exercise its insert/update/describe branches meaningfully (idempotency, map-drift, anchor-only-describe), and `tourist_identity_uninstall_plan()` similarly needs multi-case fixtures (leaf-before-region, null-count, item-holding) to triangulate real branching logic per Strict TDD's mandatory triangulation rule. Splitting mid-unit would leave some RED tests without their GREEN in the same PR, or force fixture duplication across PRs.
  - **Recommendation**: `size:exception` for PR 2, OR split at the next apply pass into PR 2a (tree_plan + leaf_ids + resolve_slug + snapshot/restore ≈ 380 lines) / PR 2b (uninstall_plan + prune_ids + reapply_message + showcase_link_needed array support ≈ 173 lines) if the maintainer prefers strictly-under-400 PRs. This apply batch implemented the whole Unit 2 as assigned; the split, if desired, is a delivery-time decision, not a re-implementation.

## TDD Cycle Evidence (Unit 3)

Unit 3 is primarily Osclass-bound glue (`index.php`), which strict TDD treats as "verified by `php -l` + vendor API confirmation," not as new decision logic requiring its own RED→GREEN cycle (see design.md: "All decisions live in tourist-identity-lib.php; this file only talks to Osclass state"). Exactly 2 pieces of genuinely new decision logic were required for the wiring and were extracted into the lib with a full cycle:

| Task | Function(s) | Test File | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-------------|-----------|------------|-----|-------|-------------|----------|
| 3.1 | `tourist_identity_tree_locales()` | `tests/test_tourist_showcase.php` | ✅ baseline 101/101 green before edit | ✅ `Call to undefined function tourist_identity_tree_locales()` (exit 255) | ✅ `Tourist showcase checks passed.` (exit 0) after implementation | ✅ 3 cases: both locales installed (plus an unrelated one, ignored), only one of the two installed, neither installed (empty result) | ➖ None needed — one-line `array_intersect` |
| 3.1 | `tourist_identity_rows_excluding_ids()` | same | ✅ (continuation of green run) | ✅ `Call to undefined function tourist_identity_rows_excluding_ids()` (exit 255) | ✅ Passed after implementation | ✅ 2 cases: excluding 2 of 4 rows (order preserved), excluding nothing (returns the exact same list) | ➖ None needed |

### RED evidence

```
$ php tests/test_tourist_showcase.php
PHP Fatal error:  Uncaught Error: Call to undefined function tourist_identity_tree_locales() in
/home/ignacio/fewohbee/tests/test_tourist_showcase.php:663
EXIT: 255
```

### Final GREEN evidence (full suite, after both lib functions + all index.php wiring)

```
$ php tests/test_tourist_showcase.php
Tourist showcase checks passed.
EXIT: 0
```

### Lint evidence

```
$ php -l plugins/tourist-identity/index.php
No syntax errors detected in plugins/tourist-identity/index.php
$ php -l plugins/tourist-identity/tourist-identity-lib.php
No syntax errors detected in plugins/tourist-identity/tourist-identity-lib.php
$ php -l plugins/tourist-identity/tourist-identity-tree.php
No syntax errors detected in plugins/tourist-identity/tourist-identity-tree.php
$ php -l tests/test_tourist_showcase.php
No syntax errors detected in tests/test_tourist_showcase.php
```

### Test Summary (Unit 3)

- **Total tests written**: 5 new `expect_true` assertions (3 for `tourist_identity_tree_locales()`, 2 for `tourist_identity_rows_excluding_ids()`).
- **Total tests passing**: all (5 new + 101 pre-existing = 106 assertions in one exit-0 run).
- **Layers used**: Unit (5 new) — pure functions, no DB/HTTP boundary. `index.php`'s wiring itself has no automated test layer in this repo (no Osclass bootstrap/test DB available to this agent) — verified by `php -l` plus a manual trace against the exact vendor APIs it calls (see "Vendor API Usage" and "Manual Trace" below).
- **Approval tests** (refactoring): 1 — `tourist_identity_apply_categories()` was refactored (its call site, not the pure `tourist_identity_category_plan()` function) to exclude tree-managed ids before planning; the pre-existing Phase 1.2 assertions (lines 90–115) against `tourist_identity_category_plan()` itself are unchanged and still pass, proving the pure function's contract was not touched, only how `index.php` now calls it.
- **Pure functions created**: 2 new (`tourist_identity_tree_locales`, `tourist_identity_rows_excluding_ids`), both zero side effects, standalone-loadable.

## Vendor API Usage (index.php, Unit 3) — every Osclass call site and its source

| Call | Used for | Vendor source |
|---|---|---|
| `Category::newInstance()->listAll()` | Fresh full category-rows snapshot (`$rows` in `apply_categories()`, `apply_tree()`, `restore_tree()`) | `Category.php:432-434` (delegates to `listWhere()`); `listWhere()` bypasses its object-cache read whenever `OC_ADMIN` is true (`Category.php:119`), which is always true on the Configure screen, so every call in this flow is a fresh query |
| `Category::newInstance()->findByPrimaryKey($id)` | Anchor row read, once, before any tree write, in `tourist_identity_ensure_tree_snapshot()` | `Category.php:625-703` |
| `Category::newInstance()->findBySlug($slug)` | Slug-collision lookup (`tourist_identity_owner_of_slug()`, passed as the `$ownerOf` callable to `tourist_identity_resolve_slug()`) | `Category.php:530-551` |
| `Category::newInstance()->insert($fields, array())` | New region/leaf row insert (empty `$aFieldsDescription` so the vendor's own non-self-excluding slug generator never runs; this plugin's own `tourist_identity_resolve_slug()` + `tourist_identity_write_description()` write descriptions instead) | `Category.php:900-921` |
| `Category::newInstance()->insertDescription($fields)` | Description row insert when `tourist_identity_write_description()`'s update matched 0 rows | `Category.php:931-935` |
| `Category::newInstance()->update($fields, $where)` (base `DAO::update()`) | `b_enabled`/`fk_i_parent_id`/`i_position` changes on `t_category` (tree plan updates, disable, snapshot_tree position restore) — field-whitelist-checked against `Category`'s own `$fields` array | `DAO.php:168-190`; whitelist source `Category.php:55-65` |
| `Category::newInstance()->deleteByPrimaryKey($id)` | Deleting a plugin-created row with 0 items, strictly in the plan's leaves-before-regions order | `Category.php:774-796` |
| `Category::newInstance()->dao` (public property, inherited from `DAO`) | Raw `select()/from()/where()/whereIn()/get()/update()` for `t_category_description` reads/writes and the `t_item` `COUNT(*)` query, since neither table has its own model class | `DAO.php:32` (`public $dao;`); pattern already used by `Field::newInstance()->dao->update(...)` in `plugins/tourist-showcase/tourist-showcase.php:36` |
| `$dao->select()/from()/where()/whereIn()/get()` | Building the raw `t_category_description`/`t_item` `SELECT` in `tourist_identity_read_descriptions()`/`tourist_identity_item_count()` | `DBCommandClass.php:216-230` (select), `:390-426` (whereIn), `:1265-1280` (get) |
| `$dao->update($table, $set, $where)` | Direct `t_category_description` update in `tourist_identity_write_description()`, returning `affectedRows()` (int) or `false` | `DBCommandClass.php:1030-1061` |
| `osc_get_locales_all('ALL', true)` | Resolving which locale codes actually exist, for `tourist_identity_installed_tree_locales()` | `hLocale.php:150-173` |
| `osc_get_preference()/osc_set_preference()/osc_get_preference_section()/osc_delete_preference()` | `category_map`, `snapshot_tree`, `last_reapply_changes` prefs | `hPreference.php:1763-1819` (backed by `Preference::replace()`, `Preference.php:202-212`) |
| `osc_update_cat_stats()` | Called after any category mutation in `apply_categories()`, `apply_tree()`, `restore_tree()` | cited in design.md as `utils.php:2277-2296`; call pattern already established in this same file's pre-existing `tourist_identity_apply_categories()`/`tourist_identity_restore_categories()` |
| `osc_cache_flush()` | Already called once at the end of `tourist_identity_apply()`/`tourist_identity_uninstall()` (unchanged call sites); not duplicated inside the new tree functions | `hCache.php:58` |
| `t_category`/`t_category_description`/`t_item` column/PK/FK facts (`i_position` default, `s_slug` NOT NULL but not unique, `t_category_description` PK is `(fk_i_category_id, fk_c_locale_code)`, `fk_c_locale_code` FK to `t_locale`) | Confirms `Category::insert()`'s raw-value handling is safe, and that a slug "collision" is a UX convention (`tourist_identity_resolve_slug()`), never a DB constraint | `installer/struct.sql:210-251` |

## Manual Trace (install → upgrade Re-apply ×2 → uninstall), against the given live starting state

Starting state per orchestrator: 1.0.0 installed, category 47 enabled at root named "Alquiler Vacacional" (es_ES/en_US), 94 defaults disabled, zero items anywhere, `tourist_showcase.category_ids = "47"`. This agent has no DB/site access, so this is a logical trace against the vendor source read above, not an executed run (Phase 4's `[USER][AUTH REQUIRED]` steps own the real execution).

1. **First Re-apply after deploying this code** (`tourist_identity_apply()`): `tourist_identity_ensure_tree_snapshot()` finds no `snapshot_tree` pref yet → reads 47 via `findByPrimaryKey()` (safe: called before any mutation, so no staleness risk from `Category`'s per-request in-memory cache) → reads its es_ES/en_US descriptions → saves `snapshot_tree` (this is the "supplementary snapshot" the Idempotent-Apply/Snapshot-on-Install requirements call for). `apply_categories()`: `tree_ids = [47]` (map still empty) → excludes only 47 → the 94 already-disabled defaults produce an empty plan (0 changes) — critically, this does **not** touch 47 at all, leaving it entirely to the tree plan. `apply_tree()`: `tourist_identity_tree_plan()` returns 56 inserts (5 regions + 51 leaves — Buenos Aires is the anchor, never inserted), 1 update for 47 (repositioning to `i_position = 0`, if it wasn't already), 2 describes for 47 (renaming to "Buenos Aires" in both locales). Each insert is executed, its category id is saved into `category_map` **immediately**, and its descriptions are written via `tourist_identity_write_description()` (insert path, since the row is brand new). `apply_showcase_link()`: `tourist_identity_leaf_ids()` now resolves all 51 leaf ids from the just-populated map; `tourist_showcase_selected_categories()` still returns `[47]`; `tourist_identity_showcase_link_needed([47], <51 ids>)` is `true` → `tourist_showcase_save_categories(<51 ids>)` relinks the 5 metadata fields and rewrites `category_ids`. Total changes > 0 → inline message shows a positive count. **Task 4.3 satisfied.**
2. **Second Re-apply** (idempotency check): `ensure_tree_snapshot()` is now a no-op (pref exists). `apply_categories()`: `tree_ids` now excludes 47 **and** all 56 created ids → still only sees the 94 defaults, still all disabled → 0 changes (this is exactly why the tree-ids exclusion was necessary: without it, this step would re-disable every newly created row on every re-apply, since the pre-existing `tourist_identity_category_plan($rows, $keepId)` disables everything except `$keepId`). `apply_tree()`: every region/leaf key now resolves to an existing row via the map, with matching flags/position/descriptions already correct → `tourist_identity_tree_plan()` returns empty insert/update/describe (this is Unit 2's already-tested "fully-applied idempotent rerun" case, Case B) → 0 changes. `apply_showcase_link()`: selected ids now already equal the 51 leaf ids (set-compare, order-independent) → 0 changes. Total changes = 0 → inline message reads exactly `"Re-apply complete: 0 change(s)."`. **Task 4.4 and the "Second re-apply shows zero changes" scenario satisfied.**
3. **Uninstall** (assuming zero items everywhere, as given): the existing `tourist_identity_restore_categories()` restores the original 95 rows' `b_enabled`/`fk_i_parent_id` (47's flags/parent only — its name/description/position are untouched by this call, exactly as before this change). `tourist_identity_restore_tree()` then: counts items for all 56 map ids (all 0, per the given state) → `tourist_identity_uninstall_plan()` classifies all 51 leaves as deletable (0 items) and, once their leaves are gone, all 5 regions as deletable too (0 items, no surviving children) → `plan['delete']` holds all 56 ids in leaves-before-regions order → each is deleted via `deleteByPrimaryKey()` in that exact order (so the vendor's own recursive cascade never has anything left to cascade into — every leaf is already gone by the time its region is deleted) → `plan['map']` is empty → the `category_map` pref is deleted outright. Since `plan['delete']` is non-empty and `tourist-showcase` is active, `tourist_showcase_selected_categories()` (the 51 leaf ids) is pruned of all 56 deleted ids → empty result → `tourist_showcase_save_categories([])` clears the showcase linkage and rewrites `category_ids` to `""`. Finally, `snapshot_tree` restores 47's original `i_position` and both locales' original name/description/slug (its `b_enabled`/parent were already restored above) → 47 is exactly "Alquiler Vacacional" again, at its original position, and `snapshot_tree`/`category_map` prefs are deleted. **Every "Restore on Uninstall" scenario, including the new "Showcase linkage is pruned of deleted categories" scenario, is satisfied by this trace.** (The general disable-when-in-use branch, for a leaf/region that does hold items, is not exercised by this specific zero-items starting state, but is already proven by Unit 2's pure-function tests for `tourist_identity_uninstall_plan()`, which this executor calls unchanged.)

## Work Unit Evidence (Unit 3)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `php tests/test_tourist_showcase.php` → RED confirmed for both new lib functions (undefined-function fatal, exit 255), then GREEN after implementation; final full run after all wiring: **106/106 assertions passing, exit 0** |
| Runtime harness command/scenario and exact result | `php -l` on all 4 touched PHP files (`index.php`, `tourist-identity-lib.php`, `tourist-identity-tree.php`, `tests/test_tourist_showcase.php`) — all clean, no syntax errors. The real runtime boundary (Osclass admin panel + live DB) is explicitly out of scope for this agent (orchestrator instruction: do not touch `app/`, `data/`, the DB, or the live site) and is reserved for Phase 4's `[USER][AUTH REQUIRED]` steps (4.3/4.4: Re-apply ×2 with inline count; 4.5: HTTPS `curl` verification). This agent substituted a manual trace against the exact vendor source (see "Manual Trace" above) as the strongest verification available without that access. |
| Rollback boundary | Revert `tourist_identity_apply_categories()`, and remove `tourist_identity_read_category_map()`, `tourist_identity_save_category_map()`, `tourist_identity_read_descriptions()`, `tourist_identity_item_count()`, `tourist_identity_ensure_tree_snapshot()`, `tourist_identity_installed_tree_locales()`, `tourist_identity_write_description()`, `tourist_identity_owner_of_slug()`, `tourist_identity_apply_tree()`, `tourist_identity_restore_tree()` from `index.php`; revert `tourist_identity_apply()`/`tourist_identity_uninstall()`/`tourist_identity_configure()`/`tourist_identity_apply_showcase_link()` to their pre-Unit-3 bodies; revert the plugin header to `1.0.0`; remove the 2 new pure functions + their assertions from `tourist-identity-lib.php`/`tests/test_tourist_showcase.php`; revert `README.md` and `specs/identity-setup-lifecycle/spec.md`. Units 1 and 2 (tree data, pure plan functions) are untouched and remain independently valid and green. On a site that already ran this code (per Phase 4), rollback in production is: uninstall (restores everything, including deleting/disabling the tree's created rows), or restore the pre-change DB dump and redeploy 1.0.0, exactly as design.md's Migration/Rollout section already specifies. |

## Deviations from Design (Unit 3)

- **Naming**: design.md's Osclass-bound interface list (`## Interfaces / Contracts`) names these functions without the plugin's `tourist_identity_` prefix (e.g. `_apply_tree()`, `_read_descriptions($ids)`). Every other function in `index.php`/`tourist-identity-lib.php` uses that prefix, because PHP has no per-plugin namespace and Osclass loads every active plugin's functions into one global function table — an unprefixed `_apply_tree()` would risk colliding with another plugin. Implemented as `tourist_identity_apply_tree()`, `tourist_identity_read_descriptions()`, `tourist_identity_item_count()`, `tourist_identity_ensure_tree_snapshot()`, `tourist_identity_restore_tree()`, matching the file's own established convention. Purely a naming clarification; the responsibilities are exactly as design.md describes.
- **`tourist_identity_apply_categories()` rewrite (necessary, not in design.md's task list literally, but required by its own stated architecture)**: design.md's Architecture Decisions table says the tree plan "delegates to existing `tourist_identity_category_plan($others, 0)` (all disabled)" — implementing this literally requires computing `$others` as the non-tree rows, which the pre-existing `tourist_identity_apply_categories()` did not do (it called `tourist_identity_category_plan($rows, 47)` with the **full** row list and the **old** keep-id). Left unchanged, the second Re-apply would have re-disabled every tree-created row, breaking the idempotency requirement outright (see "Manual Trace" step 2 above for why). Fixed by filtering `$rows` through the new `tourist_identity_rows_excluding_ids()` before planning, and changing the `keepId` argument from `47` to `0` (no row owns id `0`, so every filtered row is planned for disabling — exactly the "(all disabled)" design intent, using the exact same pre-existing pure function, unmodified).
- **Consolidated interleaving vs. the Data Flow diagram**: design.md's uninstall Data Flow interleaves "delete/disable created rows → save map → prune showcase" with "main snapshot restore (47 parent/flags)" *before* "snapshot_tree restore (47 descs/position)". This implementation instead runs the existing main-snapshot restore first, then a single `tourist_identity_restore_tree()` call that does both the created-rows cleanup and the `snapshot_tree` restore together. The two id sets (the original 95-row snapshot vs. the tree-created rows) are disjoint and 47's flags/parent (restored by the main snapshot) vs. its position/descriptions (restored by `snapshot_tree`) never conflict, so the final state is identical either way; this ordering was chosen only for one cohesive, testable-by-inspection function instead of splitting one routine across two interleaved call sites for no behavioral gain.
- **Change-count granularity**: `tourist_identity_apply_tree()` counts one "change" per category **row** insert/update, and one "change" per **description** update/insert (not, e.g., one change per field). This is a reasonable interpretation of "the exact count of changes made" (the spec does not define a canonical unit), consistent with how the pre-existing `tourist_identity_apply_categories()`/`tourist_identity_apply_currencies()` already count one change per row.

## Issues Found (Unit 3)

None. Baseline before this batch was 101 assertions (Units 1+2). Unit 3 added 5 more pure-function assertions, for a total of 106, all green in one run. `index.php`'s new Osclass-bound wiring has no automated test in this repo (no Osclass bootstrap/test DB available); it was verified by `php -l` and the manual trace above against the exact vendor APIs it calls.

## Workload / PR Boundary (Unit 3)

- Mode: chained PR slice (`stacked-to-main`, per tasks.md Review Workload Forecast, `delivery_strategy: auto-chain`) — this is the **last** of the 3 planned units/PRs.
- Current work unit: Unit 3 — Wiring `index.php` + README + spec (PR 3)
- Boundary: starts from Unit 2's clean baseline (101 assertions green) and ends with the full Osclass-bound wiring (`apply`/`upgrade` via Re-apply/`uninstall`/inline message), 2 new pure lib functions + their 5 triangulated assertions, the README rewrite, and the spec scenario addition — all green (`php -l` clean, 106/106 assertions). Does not touch `app/`, `data/`, the DB, or the live site; does not include Phase 4's deployment/verification steps, which require `[USER][AUTH REQUIRED]` access this agent does not have.
- Estimated review budget impact: authored diff is **331 lines in `index.php`** (324 ins / 7 del), **24 lines in `tourist-identity-lib.php`**, **38 lines in `tests/test_tourist_showcase.php`**, **123 lines in `README.md`** (99 ins / 24 del), **9 lines in `specs/identity-setup-lifecycle/spec.md`** (8 ins / 1 del) = **525 authored changed lines** (checkbox-only `tasks.md` edits excluded). This **exceeds the 400-line PR budget**, consistent with tasks.md's own forecast (`400-line budget risk: High`).
  - **Why it was not split further**: this is the final, cohesive "wiring" unit tasks.md itself defined as one PR (`PR3 wiring+README`). Splitting `index.php`'s `apply_tree()`/`restore_tree()` executors from the `apply()`/`uninstall()`/`configure()` call sites that invoke them would leave half the wiring dead code in one PR and untestable-by-lint in the other (an orphaned function with no caller still passes `php -l`, but the manual trace and the idempotency argument depend on the whole apply→re-apply→uninstall path being wired together in one piece). Splitting the README/spec docs from the code they document would leave the docs describing behavior that isn't in that PR yet.
  - **Recommendation**: `size:exception` for PR 3. This apply batch implemented the whole Unit 3 as assigned; this was also the last unit in the plan, so no further chaining is possible — the alternative to `size:exception` here is accepting a fourth PR that only separates code from its own documentation, which does not reduce reviewer cognitive load, only adds bookkeeping.
