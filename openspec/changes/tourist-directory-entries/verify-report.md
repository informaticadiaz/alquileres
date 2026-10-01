```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:2655b1ca564033969f2a83ce58c2fd090da6d2dd929affc000564ce4bb7dccb0
verdict: fail
blockers: 3
critical_findings: 3
requirements: 3/10
scenarios: 9/21
test_command: php tests/test_tourist_showcase.php
test_exit_code: 0
test_output_hash: sha256:f40ceec0e31c6c835e8e9dce3d2a8993adad12c9da34994fa4faa6138619cc91
build_command: ""
build_exit_code: 0
build_output_hash: sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

## Verification Report

**Change**: tourist-directory-entries
**Version**: N/A (no spec version header)
**Mode**: Strict TDD
**HEAD**: ce4b66c8697680983db15a8424c5bf793bf76879 (clean, branch `osclass`)

### Completeness
| Metric | Value |
|--------|-------|
| Tasks total | 68 |
| Tasks complete | 65 |
| Tasks incomplete | 3 (5.5, 5.6, 8.7) |

Task 5.6 is self-annotated in `tasks.md` as superseded/subsumed by Phase 8 (8.5–8.7) and carries no
independent remaining work. Task 5.5's content (read-only HTTPS render/no-price/guard/dropdown
checks) was independently re-performed as live evidence in this verify pass (see Spec Compliance
Matrix and live-check appendix below) — the checkbox itself should be closed by the orchestrator on
the strength of this report, but is reported here as unchecked-artifact state, not re-checked by this
agent. Task 8.7 (live removal-request submission + admin reactivate-with-confirm) was explicitly
**not performed**, per the user's own documented decision to close the change now and record it as a
pending follow-up. Per the unchecked-task rule, both 5.5 and 8.7 are listed below as CRITICAL
findings on procedural grounds, with 8.7 carrying the substantive risk.

### Build & Tests Execution
**Build**: ➖ Not applicable (`build_command: ""` in `openspec/config.yaml`; plain PHP script project, no build step)

**Tests**: ✅ 235 passed / ❌ 0 failed / ⚠️ 0 skipped
```text
$ php tests/test_tourist_showcase.php
Tourist showcase checks passed.
$ echo $?
0
```
232 `expect_true` + 3 `expect_throws` assertions, all green, re-executed fresh in this verify session
(not trusted from apply-progress's report alone). `php -l` clean on all 8 changed/created PHP files
(`plugins/tourist-directory/{index.php,tourist-directory-lib.php,views/removal-form.php,admin/requests.php}`,
`bin/tourist-directory-import.php`, `plugins/tourist-showcase/{tourist-showcase.php,tourist-showcase-lib.php}`,
`tests/test_tourist_showcase.php`).

**Coverage**: ➖ Not available (no coverage tool for this plain-PHP-assertion-script stack)

**Deployed-copy parity**: `diff -rq plugins/tourist-directory/ app/osclass/oc-content/plugins/tourist-directory/`
→ no output, exit 0. Deployed copy is byte-identical to source. ✅

### Spec Compliance Matrix

| # | Requirement | Scenario | Test/Evidence | Result |
|---|---|---|---|---|
| 1 | Directory Entry Public Rendering | Entry appears in category, search, and home with required elements | Live GET `?page=item&id=3`: label text present, `Visitar sitio oficial` link with `rel="nofollow noopener noreferrer" target="_blank"`, `Solicitar baja` link to `route=tourist-directory-removal&entry=3`, empty `<div class="price...">` (no value); live GET category `sCategory=104` (12 badges), home `/` (12 badges), `meta[1]` filter (Cabaña=6, Complejo=1) all show the entry | ✅ COMPLIANT |
| 2 | No Intermediated Contact UI | Contact chrome hidden on entry detail page | Live GET `?page=item&id=3`: a fully rendered, functional `<form id="contact_form" action=...contact_post...>` with a live CSRF token, a `send_friend` anchor (`Compartir`), and a `<div id="comments">` block with its comment form ARE present in the HTML response, merely hidden via `body.tourist-directory-entry #contact { display:none }` (CSS, confirmed in `assets/tourist-directory.css`, which itself documents: "cosmetic layer only, hiding the still-rendered ... chrome") | ❌ **FAILING** |
| 3 | Fail-Closed Contact Guards | Forged direct POSTs to contact/send-friend/comment blocked | Unit: `tourist_directory_should_block(true,...)===true`, `(null,...)===true` (lines 892–893). Live: GET `?page=item&action=contact&id=3` → `302` to the item page (same early guard/action list fires). Vendor-citation trace: CSRF check precedes each `pre_item_*_post` hook. No live forged POST attempted (not authorized) | ⚠️ PARTIAL |
| 3 | Fail-Closed Contact Guards | Marker lookup failure fails closed | Unit: `tourist_directory_should_block(null, 'anything@example.com', placeholder) === true` (line 892) — deterministic, no glue/DB dependency | ✅ COMPLIANT |
| 3 | Fail-Closed Contact Guards | Owner listing contact unaffected | Unit: `tourist_directory_should_block(false, 'owner@example.com', placeholder) === false` (line 895). No live owner (non-entry) item currently exists in production to exercise the full send-path (orchestrator DB evidence: all 25 items are directory entries) | ⚠️ PARTIAL |
| 4 | Filter Scope | Guests/bedrooms exclude entries, type filter includes them | Live: `meta[1]=Cabaña` → 6 results, `meta[1]=Complejo de departamentos` → 1 result, `meta[1]=Casa` → 404/no results, all matching the orchestrator's DB evidence exactly. Guests/bedrooms exclusion not separately live-queried this pass; relies on the structural fact that entries carry no guest/bedroom meta values (no custom filter code exists for this side) | ⚠️ PARTIAL |
| 5 | Accommodation Type Vocabulary | New values available on fresh and existing sites | Live: `?page=search&sCategory=104` dropdown lists `Apart hotel` and `Complejo de departamentos` alongside all six prior values. Unit: `tourist_showcase_merge_options` idempotence and append-only-missing-values tests (lines 44–60) | ✅ COMPLIANT |
| 6 | Removal Request Form | Valid submission deactivates the entry and records the request | Unit: `tourist_directory_removal_decide(...) === 'accept'` (default-flags case, line 1174); insert-then-retire ordering documented and traced against vendor citations, not executed live. Live end-to-end submission is task 8.7, explicitly deferred by the user | ⚠️ PARTIAL |
| 6 | Removal Request Form | Missing required relation is rejected | Unit: `tourist_directory_validate_removal(['relation'=>''])['error'] === 'invalid_relation'` (line 1129); `removal_decide` reaches `invalid` before any write, so no glue risk on this path | ✅ COMPLIANT |
| 6 | Removal Request Form | CSRF failure, honeypot trip, or throttle rejects the submission | Unit: honeypot/throttle branches of `removal_decide` fully table-tested (lines 1164–1177), no write occurs on any of these branches per the decision function's own contract. CSRF itself is unmodified vendor `osc_csrf_check()`, confirmed live elsewhere (admin route 302 to login only after vendor security checks). No live forged/honeypot/throttled POST attempted (not authorized) | ✅ COMPLIANT (CSRF leg not independently live-tested by this plugin, relies on vendor mechanism) |
| 6 | Removal Request Form | Duplicate request on an already-removed entry is idempotent | Unit: `removal_decide(blocking_request_exists=true) === 'already_requested'` (line 1168). The "same confirmation, no state leak" guarantee depends on a glue-level shared-message-bucket choice (`index.php`) documented in apply-progress but not executed live | ⚠️ PARTIAL |
| 7 | Removal Request Admin | Admin views removal requests with reply contact restricted | Live: the real admin controller URL (`oc-admin/index.php?page=plugins&action=renderplugin&file=tourist-directory%2Findex.php`) returns `302` to `oc-admin/index.php?page=login` without a session, confirming the auth gate. Screen content (reply-contact-only-here) verified by code review of `admin/requests.php`, not by an authenticated live render | ⚠️ PARTIAL |
| 7 | Removal Request Admin | Admin marks a request processed | Unit: `tourist_directory_admin_transition('pending','mark_processed',0) === ['ok'=>true,'status'=>'processed']`-equivalent (line 1182). Glue (DB update + PRG) not exercised live | ⚠️ PARTIAL |
| 7 | Removal Request Admin | Admin explicitly reactivates an entry for an abusive request | Unit: `admin_transition('pending'/'processed','reactivate',1) === 'rejected'`, `confirm=0 → 'confirm_required'` (lines 1184–1186). This is exactly what task 8.7 would have exercised live; explicitly deferred | ⚠️ PARTIAL |
| 8 | Seed Importer Contract | Initial import creates entries without email | **Real production evidence**: dry-run reported 25 create/5 skip; operator ran `--apply` (task 8.5); orchestrator DB evidence confirms 25 active items, all placeholder `.invalid` emails, no email-send code path exists (`ItemActions::add()` admin-suppressed). Unit: `tourist_directory_plan()` create-action case | ✅ COMPLIANT |
| 8 | Seed Importer Contract | Re-run is idempotent and updates changed fields | **Real production evidence**: after the type-vocabulary fix, re-import reported 25 update/0 create — no duplicates, existing entries updated. Unit: `plan()` noop/update-action cases | ✅ COMPLIANT |
| 8 | Seed Importer Contract | Baja and anuncio_propio rows deactivate entries reversibly | Unit: `plan()` retire-baja/retire-anuncio_propio cases (apply-progress U3 TDD evidence). Not yet exercised against real production data — orchestrator DB evidence shows all 25 items still active, none retired | ⚠️ PARTIAL |
| 8 | Seed Importer Contract | Importer skips and reports an entry with a removal request | Unit: `plan()` with `removal_seed_ids` — r1/r2/r3 all skip with `reason==='removal_requested'`, `removal_blocked===['r1','r2','r3']` even with `allow_reactivate` set (lines 1216–1220). Zero removal requests exist in production to exercise this live (orchestrator DB evidence: 0 removal requests) | ⚠️ PARTIAL |
| 9 | Production Import Gate | Import blocked without an available removal channel | Unit: `tourist_directory_cli_should_refuse($apply=true, $installed=true, $channelReady=false, ...) === true`-equivalent (table-driven, lines ~1091-1104) | ✅ COMPLIANT |
| 9 | Production Import Gate | Import proceeds once the removal channel is available | **Real production evidence**: orchestrator DB evidence confirms `schema_version=2`, `t_directory_removal_request` exists, and the real `--apply` import (task 8.5) succeeded under this exact condition | ✅ COMPLIANT |
| 10 | Reversible Uninstall | Uninstall deactivates without deleting | No unit test exists for `tourist_directory_uninstall()`/`_disable()` (100% Osclass-bound glue, acknowledged as untestable by this harness in every apply batch). Not exercised in production (orchestrator: "Uninstall not exercised in production"). Covered only by static code review and vendor-lifecycle citations (`Plugins::deactivate()`→`ItemActions::deactivate()`) | ❌ **UNTESTED** |

**Compliance summary**: 9/21 scenarios fully COMPLIANT, 10/21 PARTIAL (decision logic proven by a
passing unit test, effectful glue/live-flow unverified), 1/21 FAILING, 1/21 UNTESTED.

### Correctness (Static Evidence)
| Requirement | Status | Notes |
|---|---|---|
| Marker table schema, migration steps | ✅ Implemented | `_schema_steps`/`ensure_schema` unit-tested; live DB evidence confirms `schema_version=2`, both tables exist, `ip_salt` is 64 hex chars |
| Placeholder contact email / auto-link guard | ✅ Implemented | DB evidence: all 25 `s_contact_email` values are the per-install `.invalid` placeholder; `ItemActions::edit()`'s independent overwrite path re-verified in U3 (apply-progress) |
| Price suppression | ✅ Implemented | DB evidence: all `i_price` NULL; live page shows an empty price container, no value |
| CSRF/honeypot/throttle decision logic | ✅ Implemented | Fully unit-tested, table-driven, exact precedence order matches spec |
| Contact/comment/send-friend chrome suppression | ❌ Not implemented as specified | CSS-only hiding; markup (including a live, valid CSRF token) still renders — see CRITICAL findings |
| Uninstall/disable mass-deactivation | ⚠️ Unverified | Code/vendor-citation only, no test of any kind |

### Coherence (Design)
| Decision | Followed? | Notes |
|---|---|---|
| "CSS alone is cosmetic; the guards are the enforcement" (design.md, Rendering row) | ✅ Yes, but conflicts with spec | The design's own rationale explicitly frames CSS as cosmetic-only, which is exactly why the underlying markup still renders — this is a genuine **spec-vs-design conflict**, not an implementation bug relative to design.md. The literal spec wording ("MUST NOT render... no contact form... is rendered") is stricter than what design.md committed to build. |
| Insert-before-retire ordering for removal requests | ✅ Yes | Confirmed in code (`tourist_directory_init_custom_removal_post()`); not exercised live |
| `--allow-reactivate` never auto-reactivates a blocked (removal-requested) entry | ✅ Yes | Unit-tested at the `plan()` level (r1/r2/r3 case) |
| Reactivation requires explicit `confirm=1`, never automatic | ✅ Yes | Unit-tested via `_admin_transition` |
| Uninstall keeps the table/pref, never drops data | ⚠️ Plausible, unverified | Matches vendor citations; no runtime evidence either way |

---

### TDD Compliance
| Check | Result | Details |
|-------|--------|---------|
| TDD Evidence reported | ✅ | Present in `apply-progress.md` for U1–U3, U6, U7 with RED/GREEN/TRIANGULATE/SAFETY NET columns |
| All tasks have tests | ⚠️ | Every pure-function task has a unit test; every glue/Osclass-bound task (install/lifecycle/render hooks/CLI bootstrap/routes/views/admin POST handlers) has none — consistently documented across every batch as "no Osclass runtime available to this harness" |
| RED confirmed (tests exist) | ✅ | 235 assertions found in `tests/test_tourist_showcase.php`, verified present and re-run in this session |
| GREEN confirmed (tests pass) | ✅ | 235/235 pass, exit 0, re-executed fresh (not trusted from the prior report) |
| Triangulation adequate | ✅ | Table-driven precedence tests (`removal_decide`: 10 cases; `plan()`: 12+ cases; `admin_transition`: 7 cases; `cli_should_refuse`: multi-case) |
| Safety Net for modified files | ✅ | apply-progress documents full-suite-green-before-change for every batch |

**TDD Compliance**: 5/6 checks passed (glue-layer test gap is structural to this stack, not a process failure)

---

### Test Layer Distribution
| Layer | Tests | Files | Tools |
|-------|-------|-------|-------|
| Unit | 235 | 1 (`tests/test_tourist_showcase.php`) | Plain PHP assertion script (no framework) |
| Integration | 0 | 0 | Not installed for this stack |
| E2E | 0 | 0 | Not installed for this stack; substituted this pass by manual read-only `curl` GET checks against the live production site |
| **Total** | **235** | **1** | |

---

### Changed File Coverage
Coverage analysis skipped — no coverage tool detected for this plain-PHP-assertion-script stack.

---

### Assertion Quality
Scanned `tests/test_tourist_showcase.php` (1254 lines, 235 assertions) for banned patterns: no
tautologies (`expect_true(true)`), no assertions without a production-code call, no smoke-test-only
patterns, no CSS-class/implementation-detail-only assertions, no mock-heavy tests (this stack has no
mocking — every assertion calls a real `tourist_directory_*`/`tourist_showcase_*` function directly).
Loops over literal fixture arrays (`foreach (array(...) as $c)`) are all non-empty, hand-authored
literals, not query results that could silently be empty — not ghost loops.

**Assertion quality**: ✅ All assertions verify real behavior

---

### Quality Metrics
**Linter**: ➖ Not available (no linter configured beyond `php -l`, which is clean on all 8 changed/created files)
**Type Checker**: ➖ Not available (PHP 8.5, no static analysis tool configured for this project)

---

### Issues Found

**CRITICAL**:
1. **Task 8.7 incomplete** — the live removal-request submission + admin reactivate-with-confirm test was never performed. This is the only runtime proof available for the entire "Removal Request Form" and "Removal Request Admin" requirements' effectful (write) paths. The user explicitly chose to close the change now and record this as a pending follow-up; the underlying decision logic is fully unit-tested and the glue is grounded in vendor-citation review, but zero live end-to-end evidence exists for: an entry actually deactivating on submission, the request actually appearing in the admin screen, and the admin reactivate-with-confirm path actually working.
2. **Spec scenario FAILING — "Contact chrome hidden on entry detail page"** (Requirement: No Intermediated Contact UI). Live evidence at `https://alquileres.diazignacio.ar/index.php?page=item&id=3` shows a fully rendered, functional contact form (with a live, valid CSRF token), a working "Compartir" (send-friend) link, and a comments section — all present in the HTML response and merely hidden with CSS `display:none`. The implementation followed design.md's own documented choice ("CSS alone is cosmetic"), but this contradicts the spec's literal RFC-2119 text ("MUST NOT render... no contact form... is rendered"). This is a genuine spec-vs-design conflict that only a live check could surface (no unit test can exercise Osclass theme rendering). Recommend either: (a) amend the spec wording to "MUST NOT be visible/reachable" if the CSS+guard approach is intentionally accepted, or (b) change the implementation to server-side omit this markup on a confirmed entry (e.g., skip `doView('item-contact.php')`-equivalent inclusion, or filter it out in the theme hook) to match the literal spec.
3. **Spec scenario UNTESTED — "Uninstall deactivates without deleting"** (Requirement: Reversible Uninstall). `tourist_directory_uninstall()`/`_disable()` have zero test coverage of any kind — no unit test (100% Osclass glue) and no live exercise (explicitly not performed in production). The mechanism is a direct wrapper around the vendor's own `ItemActions::deactivate()`, which lowers real-world risk, but per this protocol's rule a required scenario with no passing covering test is CRITICAL regardless of perceived risk.

**WARNING**:
1. Task 5.5 (read-only HTTPS verification) is unchecked in `tasks.md`, but its substance was independently re-verified live in this very report (rendering, no price, dropdown values, guard GET-redirect). Recommend the orchestrator close this checkbox referencing this verify-report rather than re-running it.
2. Task 5.6 is unchecked but self-annotated in `tasks.md` as superseded by Phase 8 — no independent remaining work; recommend striking it or marking it explicitly "N/A — superseded" rather than leaving a bare unchecked box.
3. Ten scenarios (see Spec Compliance Matrix) are PARTIAL: the pure decision function is unit-tested and passing, but the effectful glue (DB writes, actual page renders behind an admin session, actual email non-send) has not been exercised at runtime. This is consistent across every apply batch's own documentation ("no Osclass runtime available to this harness") and is architecturally expected for this stack, not a sign of a defect — but it does mean spec-scenario compliance rests partly on static/vendor-citation review rather than executed proof.
4. "Baja/anuncio_propio retire reversibly" and "Importer skips a removal-blocked seed id" (Requirement: Seed Importer Contract) have never been exercised against real production data — the DB currently has zero retired entries and zero removal requests, so these code paths, while unit-tested, remain unproven in production.
5. Design decision on the removal form's message-bucket strategy (same confirmation text for `accept`/`accept_retired`/`already_requested`/`honeypot`) is documented in `apply-progress.md` and design.md but implemented in glue (`index.php`), not covered by a unit test that exercises the actual bucket-selection code, only by the pure `removal_decide()` outcome.
6. Two design.md "Open Questions" remain genuinely open and unresolved by this change: the throttle limits (5/h per IP, 3/24h per entry) and retention periods (30d/180d) are unconfirmed as acceptable, and a legal review of the Ley 25.326 privacy-note text is still pending.
7. Filter-scope live verification (Requirement: Filter Scope) covered the type-filter side fully but did not separately live-query the guests/bedrooms-exclusion side; it relies on the structural absence of those meta values on entries rather than an explicit live filter query.

**SUGGESTION**:
1. The live-rendered removal form (`views/removal-form.php`) emits the CSRF hidden `octoken` input twice with the identical value (confirmed via `curl`), while the item contact form on the same site emits it once. The view calls `osc_csrf_token_form()` exactly once (line 55); the duplication appears to originate in vendor internals for this specific route type. Harmless (same value, CSRF check still passes), but worth a one-line vendor-behavior note in the plugin README for future maintainers.
2. Separate finding, out of scope for this change (per the orchestrator's own framing): the accommodation-type filter is ignored when a visitor searches an entire region rather than a specific leaf destination, because showcase fields are linked only to leaf destinations. Not caused by this change; worth its own follow-up.
3. Consider adding a lightweight unit test around the removal form's "generic response regardless of entry existence" behavior at the glue layer (e.g., a pure function extracted from `views/removal-form.php`'s branching) so the "no error reveals entry's prior removal state" guarantee has direct test coverage beyond the `removal_decide()` outcome alone.

### Verdict
**FAIL** — three CRITICAL findings block a clean archive: task 8.7's live removal/reactivation flow was never executed (explicit, disclosed user deferral), the "No Intermediated Contact UI" spec scenario fails live inspection (chrome is CSS-hidden, not server-side omitted — a spec-vs-design conflict, not a functional security gap, since the fail-closed POST guards remain independently verified), and "Reversible Uninstall" has zero test coverage of any kind. All core CRUD/import/guard/validation logic is solidly green (235/235 unit assertions, `php -l` clean, deployed copy byte-identical to source, and three scenarios are backed by real production `--apply` evidence). The FAIL verdict reflects protocol-strict scenario/task accounting; none of the three CRITICAL items indicate a live security incident — the two most safety-relevant properties (no email/comment ever sent to the complex, and CSRF/honeypot/throttle decisions) are independently proven. Recommend the orchestrator/user jointly decide whether to accept these three items as documented exceptions before archiving, or route back to `sdd-apply`/manual verification to close task 8.7 and reconcile the contact-chrome spec-vs-design conflict.

## Orchestrator addendum and accepted exceptions (user decision, 2026-10-01)

- **Contact chrome (CRITICAL 2) — resolved by spec amendment.** The user chose to accept CSS hiding: the requirement "No Intermediated Contact UI" now requires the chrome to be neither visible nor usable; vendor markup may remain only if hidden with `display: none` and blocked server-side by the fail-closed guards (unit-tested decisions, live GET contact redirect 302, vendor hook ordering). Removing it at the source would require editing the sigma theme or buffering page output.
- **Task 8.7 (CRITICAL 1) — accepted as a documented follow-up.** The user explicitly chose to close the change without the live removal-request test; it is recorded as pending in `CODEX_STATE.md`.
- **Uninstall (CRITICAL 3) — accepted exception.** Not exercisable in production without undoing the deployment; covered by code review. Pre-deploy backups: `data/osclass/backups/pre-tourist-directory-20260930T120305.sql`, `pre-removal-form-20260930T144153.sql`.
- Approved for archive with these exceptions. Follow-ups: live removal test; type filter ignored on whole-region search (pre-existing, fields linked to leaves only); site mail; legal review of the disclaimer and privacy note; throttle/retention defaults confirmation.
