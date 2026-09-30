```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:85894571b80ca1edc771ea5ac4efebd15b2dfa7348a012a95c23f6099f8272aa
verdict: fail
blockers: 0
critical_findings: 0
requirements: 7/9
scenarios: 15/21
test_command: php tests/test_tourist_showcase.php
test_exit_code: 0
test_output_hash: sha256:f40ceec0e31c6c835e8e9dce3d2a8993adad12c9da34994fa4faa6138619cc91
build_command: N/A (no build step for this stack per openspec/config.yaml)
build_exit_code: 0
build_output_hash: sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

## Verification Report

**Change**: tourist-destination-categories
**Version**: N/A (no versioned spec revision)
**Mode**: Strict TDD

**Scope of this pass**: Full re-verification after Phase 4 (gated deployment) was authorized and executed by the user (commit `7484701`, tasks 4.1–4.5 all `[x]`). This pass independently re-runs the local test suite and `php -l`, re-diffs the deployed plugin copy, and re-checks live HTTPS evidence with read-only GET requests only (no POST/login/admin/DB access; `data/osclass/credentials.env` and `app/osclass/config.php` were never read). Orchestrator-reported DB-level facts (category counts, `t_meta_categories` links, `category_map`/`snapshot_tree` prefs) are accepted here as **orchestrator evidence**, labeled as such, not independently re-queried by this pass (this agent has no DB access).

### Completeness
| Metric | Value |
|--------|-------|
| Tasks total | 23 (5 + 11 + 9 minus tasks.md's own header rows) — see note |
| Tasks complete (checked) | 23/23 (Phases 1–3: 1.1–1.5, 2.1–2.11, 3.1–3.9 = 25 items; Phase 4: 4.1–4.5 = 5 items; all `[x]` in `tasks.md`) |
| Tasks incomplete | 0 |

Note: `tasks.md` lists 25 checkboxes across Phases 1–3 plus 5 in Phase 4 = 30 total checkboxes, all `[x]`. No unchecked task found.

**Documentation drift (non-blocking)**: `apply-progress.md`'s top "Status" line still reads "Unit 4 (Phase 4: Deployment...) — not started (out of scope for this batch...)". This is stale — `tasks.md` and `CODEX_STATE.md` (commit `7484701`, authored by the user, not this agent) confirm Phase 4 is complete. The apply-progress artifact was written before deployment and was never updated afterward. Recommend the archive phase reconcile this before the change is archived, so the artifact trail doesn't self-contradict. Not a functional defect.

### Build & Tests Execution
**Build**: ➖ Not applicable — `build_command` is empty in `openspec/config.yaml`; plain-PHP-script plugin ecosystem, no build step.

**Tests**: ✅ 106+ assertions (per `apply-progress.md`'s running count), all passed (re-run independently by this pass)
```text
$ php tests/test_tourist_showcase.php
Tourist showcase checks passed.
$ echo $?
0
```

**Minor count discrepancy (non-blocking)**: `apply-progress.md` reports a running total of 106 assertions (45 pre-existing + 17 Unit 1 + 39 Unit 2 + 5 Unit 3). An independent static count of `expect_true(`/`expect_throws(` call sites in `tests/test_tourist_showcase.php` (excluding the 2 harness function definitions) finds 119. The difference is explained by several `foreach` loops that assert once per tree region/leaf at runtime (e.g. the 6-region and 51-leaf shape checks) — one source call-site, many runtime executions — so the two counting methods are not directly comparable and the discrepancy does not indicate missing coverage. The suite as a whole passes (exit 0) either way. Flagged only for documentation precision.

```text
$ php -l plugins/tourist-identity/index.php
No syntax errors detected in plugins/tourist-identity/index.php
$ php -l plugins/tourist-identity/tourist-identity-lib.php
No syntax errors detected in plugins/tourist-identity/tourist-identity-lib.php
$ php -l plugins/tourist-identity/tourist-identity-tree.php
No syntax errors detected in plugins/tourist-identity/tourist-identity-tree.php
$ php -l tests/test_tourist_showcase.php
No syntax errors detected in tests/test_tourist_showcase.php
```

```text
$ diff -r plugins/tourist-identity/ app/osclass/oc-content/plugins/tourist-identity/
(no output — byte-identical)
```

**Coverage**: ➖ Not available — no coverage tool configured for this stack (`coverage_threshold: 0`).

### Live HTTPS Evidence (this pass, read-only GET only)

All requests below were issued by this verify pass against `https://alquileres.diazignacio.ar/` using `curl` (GET only).

| Check | Command | Result |
|---|---|---|
| Homepage status | `curl https://alquileres.diazignacio.ar/` | HTTP 200 |
| Region order + ids | `grep -oE 'sCategory=(47\|96\|97\|98\|99\|100)"' home_es.html` | in order 47, 96, 97, 98, 99, 100 ✅ |
| Region names, first-seen order | `grep -oE '(Buenos Aires\|Córdoba\|Cuyo\|Litoral\|Norte\|Patagonia)' home_es.html` | Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia ✅ |
| "Alquiler Vacacional" absent | `grep -c "Alquiler Vacacional" home_es.html` | 0 occurrences ✅ |
| en_US locale (cookie jar) | `curl -c cookies.txt .../index.php?page=language&locale=en_US` then `curl -b cookies.txt /` | HTTP 302 then 200; same 6 region names render (Spanish proper nouns unchanged, as expected) ✅ |
| Patagonia region page | `curl .../index.php?page=search&sCategory=100` | HTTP 404 (native Osclass "empty result set" behavior — zero items exist system-wide; see caveat below), `<title>Patagonia - Alquileres Temporarios</title>` present in body |
| Patagonia leaves listed, catch-all last | parsed `sCategory=` links + label text from the 404 body | 9 leaves + self = 10 distinct category links; order ends with "Otros destinos de Patagonia" ✅ |
| Publish form structure | `curl .../index.php?page=item&action=item_add` | exactly 6 `<optgroup>` (labels: Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia) and exactly 51 distinct `<option value="…">` ✅ |
| Deployed copy re-sync | `diff -r plugins/tourist-identity/ app/osclass/oc-content/plugins/tourist-identity/` | byte-identical ✅ |

**Caveat on the 404**: as in the prior verify pass for this plugin, `page=search` returns HTTP 404 whenever the queried category currently has zero items — this is Osclass's native "empty result set" behavior (confirmed by the orchestrator and consistent across all six regions), not a category-enabled/disabled discriminator. The category page still renders its full leaf list and correct `<title>` inside that 404 response body, which is the evidence actually used above.

**Not independently re-checked by this pass** (accepted as orchestrator DB evidence, not re-queried — this agent has no DB access): 6 enabled roots ordered by `i_position` 0..5 with 10/8/7/8/9/9 enabled children = 51 leaves; 94 categories `b_enabled=0`; `t_meta_categories`: 51 categories × 5 fields = 255 links; `tourist_showcase.category_ids` has 51 ids; `tourist_identity` prefs: `applied_version 1.1.0`, `category_map` (5 regions + 51 leaves, anchor 47 absent), `last_reapply_changes = 0` after the second Re-apply; `snapshot_tree` holds 47's original es_ES/en_US name and `i_position 5`; no active category slug has a numeric suffix.

### Spec Compliance Matrix

Status legend: ✅ COMPLIANT (runtime-passing test and/or live evidence proves the scenario) · ⚠️ PARTIAL (decision logic unit-tested and/or code-reviewed; live/DB proof incomplete or unavailable without undoing the production deployment) · 📋 = includes orchestrator DB evidence, not independently re-queried by this pass.

**identity-setup-lifecycle**

| Requirement | Scenario | Test / Evidence | Result |
|---|---|---|---|
| Idempotent Apply | Re-apply changes nothing | `tourist_identity_tree_plan()` Case B (idempotent full-map rerun → empty plan, `tests/test_tourist_showcase.php:413-454`); 📋 live: `last_reapply_changes = 0` after 2nd Re-apply | ✅ COMPLIANT |
| Idempotent Apply | Re-apply creates no duplicate categories | Same Case B unit test; 📋 category counts unchanged (51 leaves, 94 disabled) after two Re-applies | ✅ COMPLIANT |
| Snapshot on Install | Snapshot precedes first change | Pre-existing snapshot-build unit test (unchanged by this delta) + 📋 orchestrator DB fact (snapshot pref exists) | ✅ COMPLIANT |
| Snapshot on Install | Supplementary snapshot for already-installed sites | `tourist_identity_build_tree_snapshot()`/`tourist_identity_tree_restore_plan()` round-trip unit test (`:541-567`); 📋 live: `snapshot_tree` holds 47's original names/position | ✅ COMPLIANT |
| Restore on Uninstall | Uninstall reverts identity changes | Cannot be executed in production without undoing the live deployment. Judged by pre-existing `restore_plan`/`restore_categories` unit tests + code review of `tourist_identity_restore_tree()` | ⚠️ PARTIAL (not-runtime-provable) |
| Restore on Uninstall | Item deletion is not reverted | Same — code review only, unchanged behavior from prior change | ⚠️ PARTIAL (not-runtime-provable) |
| Restore on Uninstall | Category 47 is restored to its original identity | `tourist_identity_tree_restore_plan()` round-trip test + protected-ids test (`:648-659`, anchor never deleted/disabled) + code review of `restore_tree()`'s snapshot_tree restore path | ⚠️ PARTIAL (not-runtime-provable) |
| Restore on Uninstall | Created rows are deleted if empty, disabled if in use | `tourist_identity_uninstall_plan()` — 5 triangulated cases (zero-count delete, in-use disable+kept, null-count disable, leaf-before-region delete, region-blocked-by-surviving-leaf disable, `:568-619`) | ⚠️ PARTIAL (decision logic strongly unit-tested; DB-wired execution not exercised live — destructive to test in production) |
| Restore on Uninstall | Showcase linkage is pruned of deleted categories | `tourist_identity_prune_ids()` unit test (order/uniqueness, `:621-634`) covers the pure pruning logic; the `restore_tree()` call site that wires it to `tourist_showcase.category_ids` is code-reviewed only, not live-tested | ⚠️ PARTIAL (not-runtime-provable) |
| HTTPS-Verifiable Success | End-to-end public verification | Live: title, 6 regions + 51 leaves, publish-form leaf-only options, en_US locale all confirmed this pass; items-1/2 404/410 and "Powered by Osclass" absence verified in the prior `tourist-portal-identity` verify pass and unaffected by this change's code paths (only category structure changed) | ✅ COMPLIANT |
| Created-Category Tracking | New category id is recorded on first creation | `tourist_identity_tree_plan()` Case A (empty map → insert entries per key, `:375-412`) + 📋 live `category_map` holds all 56 created ids | ✅ COMPLIANT |
| Created-Category Tracking | Map entry is reused on subsequent runs | `tourist_identity_tree_plan()` Case B + Case C (map-drift reinsert, `:455-465`) + 📋 live idempotent 2nd Re-apply | ✅ COMPLIANT |
| Visible Re-Apply Result | Configure screen shows change count after re-apply | `tourist_identity_reapply_message()` unit test (`:638-645`); task 4.3 (inline count > 0 on first Re-apply) marked `[x]` by the user | ✅ COMPLIANT |
| Visible Re-Apply Result | Second re-apply shows zero changes | `tourist_identity_reapply_message(0)` unit test; 📋 live `last_reapply_changes = 0` matches "0 change(s)" exactly | ✅ COMPLIANT |

**portal-catalog-baseline**

| Requirement | Scenario | Test / Evidence | Result |
|---|---|---|---|
| Destination Tree Categories | Regions appear at root in confirmed order | `tourist_identity_tree()` shape unit test (6 regions, confirmed order, `:271-280`); live: `sCategory=` links in order 47/96/97/98/99/100, names match | ✅ COMPLIANT |
| Destination Tree Categories | Leaves nest under their region in relevance order | Shape unit test (first-leaf + catch-all-last assertions, `:327-356`); live: Patagonia page lists 9 leaves ending in "Otros destinos de Patagonia" | ✅ COMPLIANT |
| Destination Tree Categories | Category 47 becomes the Buenos Aires region | `tourist_identity_tree_plan()` Case A (anchor gets describe/update only, never insert); live: "Buenos Aires" renders at `sCategory=47`, "Alquiler Vacacional" appears 0 times site-wide | ✅ COMPLIANT |
| Destination Tree Categories | Native defaults stay disabled, not deleted | `tourist_identity_rows_excluding_ids()` + pre-existing `category_plan(keepId=0)` unit tests; 📋 live: 94 rows `b_enabled=0`, none deleted | ✅ COMPLIANT |
| Destination Tree Categories | Publish form only allows leaf selection | Live: exactly 6 non-selectable `<optgroup>` region labels + exactly 51 selectable `<option>`s, directly counted this pass | ✅ COMPLIANT |
| Category Linkage Preserved | Showcase fields linked to every leaf | `tourist_identity_showcase_link_needed()` array-target unit test (`:492-506`); 📋 orchestrator DB fact: `t_meta_categories` 51×5=255 links, `category_ids` has 51 ids | ✅ COMPLIANT (DB fact not independently re-queried by this pass) |
| Category Linkage Preserved | Linkage restored after showcase reinstall | Only the generic `showcase_link_needed()` set-comparison is unit-tested; the specific "showcase plugin reinstalled, `category_ids` reset to `""`" trigger was never exercised at any layer (unit, integration, or live) | ⚠️ PARTIAL (weakest scenario in this pass — no dedicated test) |

**Compliance summary**: 15/21 scenarios ✅ COMPLIANT. 6/21 ⚠️ PARTIAL — 5 are the "Restore on Uninstall" family (cannot be exercised in production without undoing the live deployment, per explicit orchestrator instruction; all backed by strong unit tests of the underlying pure functions plus code review), and 1 is "Linkage restored after showcase reinstall" (a genuine, narrower test gap — the specific reinstall trigger has no dedicated test at all, though the underlying re-apply/relink logic it would exercise is the same one already proven by the "Second re-apply shows zero changes" and "Showcase fields linked to every leaf" scenarios).

### Correctness (Static Evidence)
| Requirement | Status | Notes |
|---|---|---|
| Destination tree data | ✅ Implemented | `tourist-identity-tree.php`, 6 regions/51 leaves, matches `research.md` order per Unit 1's shape tests |
| Tree plan / uninstall plan / slug resolver / snapshot round-trip | ✅ Implemented | 8 pure functions in `tourist-identity-lib.php`, each with a full RED→GREEN cycle and 2–5 triangulated cases (see `apply-progress.md` TDD Cycle Evidence tables) |
| `index.php` wiring (`apply_tree`, `restore_tree`, `ensure_tree_snapshot`, showcase link) | ✅ Implemented, code-reviewed | Osclass-bound glue with no automated test (no DB bootstrap in this repo); verified by `php -l` + a manual trace against the exact vendor API call sites (`apply-progress.md`'s "Vendor API Usage" / "Manual Trace" sections) + this pass's live evidence for the apply path |
| Visible Re-Apply Result | ✅ Implemented | `tourist_identity_configure()` persists and renders `last_reapply_changes` inline, independent of the flash message |
| No vendor file modified | ✅ Confirmed | `git diff --stat` across the change's commits touches only `plugins/tourist-identity/`, `tests/`, `openspec/`, `README.md`; `app/` and `data/` are untouched in the committed history |

### Coherence (Design)
| Decision | Followed? | Notes |
|---|---|---|
| Tree data as a separate pure file | ✅ Yes | `tourist-identity-tree.php`, required by the lib |
| Explicit slug per node, `-2`/`-3` collision resolver | ✅ Yes | `tourist_identity_resolve_slug()`, 4 triangulated cases including self-exclusion |
| DAO update of `t_category_description` for 47's rename | ✅ Yes | `tourist_identity_write_description()` update-else-insert, per design |
| Persisted created-id map, written per insert | ✅ Yes | `category_map` pref saved immediately after each `Category::insert()` in `apply_tree()` |
| Tree plan delegates non-tree rows to existing `category_plan($others, 0)` | ✅ Yes (with a documented, necessary rewrite) | `apply_categories()` now filters `$rows` via `rows_excluding_ids()` first — design.md's own architecture required this to avoid re-disabling tree rows on every re-apply; documented as a "Deviation from Design" in `apply-progress.md`, not a silent change |
| Supplementary `snapshot_tree`, captured only if absent | ✅ Yes | `tourist_identity_ensure_tree_snapshot()` guards on pref absence |
| Uninstall delete only when own count = 0 and no surviving children, leaves before regions | ✅ Yes (unit-tested, not live-exercised) | `tourist_identity_uninstall_plan()`; see PARTIAL scenarios above |
| Inline Re-apply feedback, escaped | ✅ Yes | `tourist_identity_reapply_message()` rendered via the configure screen |
| Uninstall/restore ordering vs. the Data Flow diagram | ⚠️ Documented deviation, behaviorally equivalent | Design's diagram interleaves main-snapshot restore with tree cleanup; implementation runs them sequentially instead (disjoint id sets, no conflict) — explicitly called out in `apply-progress.md`'s "Deviations from Design" |

### TDD Compliance
| Check | Result | Details |
|-------|--------|---------|
| TDD Evidence reported | ✅ | Full RED/GREEN/TRIANGULATE/REFACTOR tables present in `apply-progress.md` for Units 2 and 3 |
| All tasks have tests | ⚠️ | Every pure decision function (tree_plan, uninstall_plan, leaf_ids, resolve_slug, snapshot round-trip, prune_ids, reapply_message, tree_locales, rows_excluding_ids) has a full RED→GREEN cycle; `index.php`'s Osclass-bound wiring functions have no direct unit test (no DB bootstrap available in this repo), consistent with design.md's own stated strategy of "verified by `php -l` + vendor API confirmation" for that layer |
| RED confirmed (tests exist) | ✅ | Verified by direct read of `tests/test_tourist_showcase.php`; RED evidence samples (undefined-function fatals, one genuine assertion-failure RED) reproduced in `apply-progress.md` |
| GREEN confirmed (tests pass) | ✅ | `php tests/test_tourist_showcase.php` exit 0, re-run independently by this pass |
| Triangulation adequate | ✅ | Every new pure function has 2–5 distinct cases exercising happy path + edge/branching paths (idempotency, map-drift, self-exclusion, leaves-before-regions, null-count, region-blocked-by-surviving-leaf) |
| Safety Net for modified files | ✅ | Baseline green run confirmed before each edit per the TDD Cycle Evidence tables; no regression on final full run |

**TDD Compliance**: 5/6 checks fully passed (the one ⚠️ is the same structural, previously-established gap as the prior verify pass: Osclass-bound wiring has no direct automated test, only `php -l` + manual trace + this session's live evidence for the parts that can be observed over HTTPS)

### Test Layer Distribution
| Layer | Tests | Files | Tools |
|-------|-------|-------|-------|
| Unit | ~119 static call-sites (see count-discrepancy note above), all pure-function-level | 1 (`tests/test_tourist_showcase.php`) | plain PHP assertion script, no framework |
| Integration | 0 | 0 | not installed |
| E2E (manual) | 1 verification pass this session, 9+ discrete live checks, plus the user's own Phase 4.3/4.4/4.5 execution | 0 automated files — `curl`/`grep` ad hoc, not a persisted E2E suite | not installed as a tool |
| **Total** | **~119 unit + 9+ live checks** | **1 test file** | |

### Changed File Coverage
Coverage analysis skipped — no coverage tool detected for this stack (`coverage_threshold: 0`).

### Assertion Quality
Scanned all `tourist_identity_*` assertions added or modified by this change (`tests/test_tourist_showcase.php:269-697`). No `mock`/`Mock` usage (pure functions, no DB boundary in these tests). All `foreach`-based assertions (destination-tree shape checks) iterate over a collection whose size is independently asserted non-empty (`count($destination_tree) === 6`) before the loop runs, so none is a "ghost loop over a possibly-empty collection." Every assertion compares against a concrete, non-trivial expected value (specific arrays/strings tied to specific inputs); no tautologies, no smoke-test-only patterns, no implementation-detail (CSS/mock-count) coupling found.

**Assertion quality**: ✅ All assertions verify real behavior

### Quality Metrics
**Linter**: ✅ No syntax errors (`php -l`) on all 4 touched PHP files, re-checked by this pass.
**Type Checker**: ➖ Not available (PHP, no static type-checker configured for this stack).

### Issues Found

**CRITICAL**: None.

**WARNING**:
1. **"Restore on Uninstall" family (5 scenarios) cannot be exercised in production without undoing the live deployment.** Per explicit orchestrator instruction, this pass judged them by unit tests of the underlying pure functions (`tourist_identity_uninstall_plan()` — 5 triangulated cases; `tourist_identity_prune_ids()`; `tourist_identity_tree_restore_plan()` round-trip; the dedicated protected-anchor test) plus code review of `tourist_identity_restore_tree()` against the exact vendor `Category::deleteByPrimaryKey()` semantics. This mirrors the exact same structural gap the prior `tourist-portal-identity` verify pass accepted as a permanent exception. Recommend the same treatment here: an explicit, documented, user-accepted exception (or a disposable staging environment exercise, if ever justified) before treating uninstall/rollback as proven.
2. **"Linkage restored after showcase reinstall" has no dedicated test at any layer.** The generic `tourist_identity_showcase_link_needed()` array-comparison is unit-tested, and the same code path is exercised (in the "resync after empty" direction) by the "Second re-apply shows zero changes" scenario, but the specific trigger this scenario describes — `tourist-showcase` reinstalled, `category_ids` reset to `""`, then Re-apply — was never simulated by a test or a live action. Lower risk than the uninstall gaps (non-destructive, and the underlying relink logic is proven), but it is a genuine, narrower coverage gap worth a follow-up unit test (`showcase_link_needed([], <51 ids>)` plus a mock of `tourist_showcase_selected_categories()` returning `[]`).
3. **`apply-progress.md`'s Status header is stale** — it still reads "Unit 4 ... not started," contradicted by `tasks.md` (all 30 checkboxes `[x]`) and by `CODEX_STATE.md`/commit `7484701` (deployment documented as done). Documentation-drift only; recommend reconciling before archive.
4. **Minor assertion-count discrepancy** — `apply-progress.md` tracks a running total of 106 assertions; an independent static count of call-sites finds 119, explained by `foreach`-based assertions executing multiple times per source line. Not a coverage gap (the whole suite still passes, exit 0), just an imprecise running count in the progress log.

**SUGGESTION**:
1. Consider adding one explicit read-only DB check to a future ops runbook (`SELECT category_ids FROM tourist_showcase`, `SELECT b_enabled, i_position FROM t_category WHERE b_enabled=1 ORDER BY i_position`) so the "Category Linkage Preserved" / tree-shape facts currently relayed only as orchestrator evidence can be independently re-queried by a future verify pass without needing a new orchestrator hand-off.
2. Consider whether a disposable staging/test Osclass instance (out of scope for this change) would let a future change exercise the uninstall/restore path end-to-end without touching the live deployment, closing WARNING 1 permanently rather than accepting it change after change.

### Verdict
**FAIL** — this is an **incomplete-evidence FAIL, not an implementation FAIL**, consistent with strict-TDD's rule that a scenario is compliant only when a covering runtime test or live check actually passed, and with this exact project's own established precedent (the prior `tourist-portal-identity` verify pass reached the same kind of verdict for the same structural reason). Zero CRITICAL findings, zero blockers. All 106+ automated assertions pass (exit 0), all 4 touched PHP files lint clean, the deployed copy is byte-identical to source, and live HTTPS evidence this pass independently confirms the entire apply-side surface: 6 regions in the correct order at the correct category ids, 51 leaves per region in relevance order with the catch-all last, category 47 correctly repurposed and "Alquiler Vacacional" gone site-wide, the publish form's 6-optgroup/51-option structure, and en_US locale rendering. 15/21 scenarios are ✅ COMPLIANT with direct runtime or live evidence. The remaining 6/21 ⚠️ PARTIAL scenarios (5 "Restore on Uninstall" + "Linkage restored after showcase reinstall") keep the totals below 21/21, which is why a strict verdict cannot be `pass` here — but none of these gaps is a discovered functional defect: every piece of decision logic behind the uninstall gaps is unit-tested with real triangulation and code-reviewed against exact vendor source, and the one narrower reinstall-linkage gap is a missing test, not a missing behavior. Recommendation: **not archive-ready on strict runtime-evidence grounds alone**, but — exactly as was done for the prior change in this same repository — the orchestrator/user may consciously accept the uninstall-family gap as a permanent, documented exception (production is live, correctly behaving, and a full backup exists at `data/osclass/backups/pre-destination-categories-<date>.sql`), and add one small unit test for the reinstall-linkage scenario, before proceeding to archive.

## Orchestrator addendum (2026-09-30, post-verify)

- "Linkage restored after showcase reinstall" now has dedicated unit coverage in `tests/test_tourist_showcase.php`: an emptied `category_ids` requires relinking all 51 leaf ids, and a relinked set (any order) needs no further change. Suite exit 0.
- `apply-progress.md` status reconciled: Phase 4 complete (backup, user sync + Re-apply ×2 with 0 changes on the second run, read-only DB/HTTPS verification, commit `7484701`).
- Remaining gap: the "Restore on Uninstall" scenario family is not runtime-provable in production without undoing the deployment; it is covered by unit tests (`uninstall_plan` incl. protected ids, `prune_ids`, tree snapshot round-trip) and code review of `restore_tree()`.

## Accepted exception (user decision, 2026-09-30)

The user explicitly accepted, as a permanent exception, that the "Restore on Uninstall" scenario family is not proven in production. Evidence: unit-tested `uninstall_plan` (including protected anchor ids), `prune_ids`, tree snapshot round-trip; code review of `restore_tree()` (parent structure read from raw `t_category`, failure disables instead of deleting, deletes only created categories with exactly 0 items and no remaining children, leaves before regions); pre-deploy backup `data/osclass/backups/pre-tourist-destinations-20260929T224115.sql`. No CRITICAL findings. Approved for archive with this exception.
