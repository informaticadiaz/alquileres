# Apply Progress: Tourist Destination Categories

## Status

Unit 1 (Phase 1: Tree Data, PR 1) — **complete**. Unit 2 (Phase 2: Pure Plans, PR 2) — **complete**. Units 3, 4 — **not started**.

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

## Remaining Tasks (out of scope for this batch, untouched)

- [ ] Phase 3 (Unit 3, PR 3): Wiring `index.php` + README + spec addition (3.1–3.9)
- [ ] Phase 4 (Deployment, [USER]/[AUTH REQUIRED]): 4.1–4.5

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
