# Apply Progress: Tourist Destination Categories

## Status

Unit 1 (Phase 1: Tree Data, PR 1) — **complete**. Units 2, 3, 4 — **not started**.

## Completed Tasks

- [x] 1.1 RED: test `tourist_identity_tree()` = 6 regions in order (Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia).
- [x] 1.2 RED: test 51 leaves, `research.md` order per region, catch-all last, unique `^[a-z0-9-]+$` keys, both locale names present.
- [x] 1.3 GREEN: create `plugins/tourist-identity/tourist-identity-tree.php` with `tourist_identity_tree()`; anchor 47 = Buenos Aires.
- [x] 1.4 Require the new file from `tests/test_tourist_showcase.php`.
- [x] 1.5 VERIFY: test green, `php -l` on the new file. Commit intentionally **deferred** — this apply batch's scope explicitly excludes commit/push.

## Remaining Tasks (out of scope for this batch, untouched)

- [ ] Phase 2 (Unit 2, PR 2): Pure plans in `tourist-identity-lib.php` (2.1–2.11)
- [ ] Phase 3 (Unit 3, PR 3): Wiring `index.php` + README + spec addition (3.1–3.9)
- [ ] Phase 4 (Deployment, [USER]/[AUTH REQUIRED]): 4.1–4.5

## Files Changed

| File | Action | Lines | What Was Done |
|------|--------|-------|----------------|
| `plugins/tourist-identity/tourist-identity-tree.php` | Created | 112 | Pure data file: `tourist_identity_tree()` returns 6 regions (Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia) in confirmed order; Buenos Aires carries `anchor => 47`; each region has `names['es_ES'\|'en_US']` and a `leaves` array of `[key, es_name, en_name]` triples in research.md order, catch-all last. |
| `tests/test_tourist_showcase.php` | Modified | +102 | Added `require` for the new tree file; added RED-then-GREEN shape assertions: region count/order/names, Buenos Aires-only anchor, per-region leaf counts (10/8/7/8/9/9 = 51), first-leaf and catch-all-last per region, ASCII key regex, both-locale-name presence, and global key uniqueness (57 = 6 + 51). |
| `openspec/changes/tourist-destination-categories/tasks.md` | Modified | 5 checkboxes | Marked 1.1–1.5 `[x]`. |

## TDD Cycle Evidence

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | `tests/test_tourist_showcase.php` | Unit | ✅ baseline green (`php tests/test_tourist_showcase.php` passed before any edit) | ✅ Written (called `tourist_identity_tree()`, undefined at that point) | ✅ Passed after 1.3 | ➖ Skipped: purely structural data file, single correct output (6 fixed regions in confirmed order), no branching logic | ➖ None needed |
| 1.2 | `tests/test_tourist_showcase.php` | Unit | ✅ (same run as 1.1) | ✅ Written (51-leaf shape, order, catch-all, key regex, uniqueness — all against the same undefined function) | ✅ Passed after 1.3 | ➖ Skipped: same reason — declarative 51-leaf table has one correct shape per research.md, no algorithmic path to generalize | ➖ None needed |
| 1.3 | n/a (production) | — | n/a (new file) | — | ✅ `php tests/test_tourist_showcase.php` → `Tourist showcase checks passed.` (exit 0) | — | — |
| 1.4 | `tests/test_tourist_showcase.php` | — | — | — | ✅ require added, same green run | — | — |
| 1.5 | — | — | — | — | ✅ `php -l plugins/tourist-identity/tourist-identity-tree.php` → `No syntax errors detected`; `php -l tests/test_tourist_showcase.php` → `No syntax errors detected` | — | — |

### RED evidence (before `tourist-identity-tree.php` existed)

```
$ php tests/test_tourist_showcase.php
PHP Fatal error:  Uncaught Error: Call to undefined function tourist_identity_tree() in
/home/ignacio/fewohbee/tests/test_tourist_showcase.php:270
EXIT: 255
```

### GREEN evidence (after `tourist-identity-tree.php` created + required)

```
$ php tests/test_tourist_showcase.php
Tourist showcase checks passed.
EXIT: 0
```

### Lint evidence

```
$ php -l plugins/tourist-identity/tourist-identity-tree.php
No syntax errors detected in plugins/tourist-identity/tourist-identity-tree.php
$ php -l tests/test_tourist_showcase.php
No syntax errors detected in tests/test_tourist_showcase.php
```

### Data-accuracy cross-check

A one-off Python script (not committed) parsed every `| Region | es_ES | en_US |` row out of
`research.md`'s destination table and confirmed all 51 es_ES/en_US strings appear verbatim inside
`tourist-identity-tree.php`. The 11 "missing" hits it reported all belonged to the unrelated
Region/Provinces/Confidence table earlier in `research.md`, not the destination table — zero
mismatches against the actual 51-row destination table.

### Test Summary

- **Total tests written**: 17 new `expect_true` assertions (Phase 1 block) — none trivial; each checks a
  specific value derived from `research.md` / `design.md` (region order, region names, anchor id, leaf
  counts per region, first-leaf and catch-all identity per region, key regex, uniqueness count).
- **Total tests passing**: all (17 new + 45 pre-existing = 62 assertions in the file, one exit-0 run).
- **Layers used**: Unit (17).
- **Approval tests** (refactoring): None — no refactoring tasks in this unit.
- **Pure functions created**: 1 (`tourist_identity_tree()`), zero side effects, standalone-loadable.

## Work Unit Evidence (Unit 1)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `php tests/test_tourist_showcase.php` → RED: `PHP Fatal error: Call to undefined function tourist_identity_tree()` (exit 255) before 1.3; GREEN: `Tourist showcase checks passed.` (exit 0) after 1.3–1.4 |
| Runtime harness command/scenario and exact result | N/A — pure data file, no DB/HTTP boundary in this unit (per tasks.md Suggested Work Units table) |
| Rollback boundary | Delete `plugins/tourist-identity/tourist-identity-tree.php`, revert the `require` line and the Phase-1 assertion block in `tests/test_tourist_showcase.php`. No other file touched; Units 2–4 untouched and unaffected. |

## Deviations from Design

None. Tree shape matches `design.md`'s `tourist_identity_tree()` contract exactly: array of regions with
`key`, optional `anchor` (Buenos Aires only, value `47`), `names` (`es_ES`/`en_US`), and `leaves` (array
of `[key, es_name, en_name]` triples). Region order and per-region leaf order/counts match `research.md`
exactly, per the orchestrator's explicit constraint.

## Issues Found

None.

## Workload / PR Boundary

- Mode: chained PR slice (`stacked-to-main`, per tasks.md Review Workload Forecast)
- Current work unit: Unit 1 — Tree data + shape tests (PR 1)
- Boundary: starts from a clean baseline (all 45 pre-existing assertions green) and ends with the new
  `tourist-identity-tree.php` file plus its 17 shape assertions green; does not touch
  `tourist-identity-lib.php`, `index.php`, `app/`, `data/`, or the live site.
- Estimated review budget impact: ~112 (new file) + 102 (test additions) = ~214 authored lines, well
  under the 400-line budget for this slice; checkbox-only edits to `tasks.md` excluded from that count.
