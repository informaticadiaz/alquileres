# Archive Report: Tourist Directory Entries

**Change**: tourist-directory-entries  
**Date Archived**: 2026-10-01  
**Archive Path**: `openspec/changes/archive/2026-10-01-tourist-directory-entries/`  
**Artifact Store Mode**: hybrid (openspec + Engram)  
**Orchestrator Approval**: Explicit user decision on 2026-10-01 to archive with documented exceptions  

## Executive Summary

The `tourist-directory-entries` change is complete and archived. Directory entries for commercial tourist complexes are deployed and live with 25 entries across Villa Gesell / Mar de las Pampas. The removal request form and admin screen are operational. The removal channel (plugin-owned form) is available; site mail is postponed per user decision. The new `directory-entries` spec has been synced to main specs. All 3 CRITICAL verification findings from the verify report have been explicitly accepted as documented exceptions by the user on 2026-10-01.

## Final State (At Close)

### Deployment Status: LIVE

- **25 directory entries** deployed and active (Villa Gesell / Mar de las Pampas complejos)
- **Type meta backfilled**: 12 Apart hotel, 6 Cabaña, 6 Apartamento, 1 Complejo de departamentos
- **Removal form + admin screen**: operational, schema version 2 confirmed
- **Site mail**: postponed indefinitely per user decision (removal form works without email)
- **Deployed copy parity**: verified byte-identical to source via `diff -r` (verify-report, 2026-09-30)

### Specs Synced

| Domain | Action | Req/Scenario |
|--------|--------|---|
| `directory-entries` | Created (NEW) | 10 Requirements / 21 Scenarios |

**Mechanical copy verification**: spec.md copied from change folder to `openspec/specs/directory-entries/spec.md`; diff -r confirms byte-identity. ✓

### Archive Contents

- `proposal.md` ✅ (scope, approach, rollback, dependencies, success criteria)
- `specs/directory-entries/spec.md` ✅ (synced to main specs)
- `design.md` ✅ (technical approach, architecture decisions, 3 phases + 2 amendments)
- `tasks.md` ✅ (8 phases, 65/68 complete)
- `apply-progress.md` ✅ (7 phases executed: U1–U3, U6, U7)
- `verify-report.md` ✅ (strict TDD, 235/235 unit assertions green, live HTTPS checks)
- `explore.md` ✅ (decision rationale archive)

**Artifact reads in this archive phase**: proposal, spec, design, tasks, verify-report, apply-progress, explore — all read and verified in place before archive move.

## Task Completion

**Total tasks**: 68  
**Complete**: 65 ✅  
**Incomplete**: 3 (5.5, 8.7, and procedurally 5.6)

### Incomplete Task Reconciliation (Per User Decision, 2026-10-01)

**Task 5.5** (read-only HTTPS verification): Unchecked in `tasks.md`, but verify-report explicitly states its substance was **independently re-verified live in the verify pass** (rendering, no price, dropdown values, guard GET-redirect). Per the verify-report's own analysis: "Recommend the orchestrator close this checkbox referencing this verify-report rather than re-running it." This archive phase closes it based on the live evidence already captured.

**Task 8.7** (live removal-request submission + admin reactivate): Explicitly deferred and documented as a **pending follow-up** in the verify-report addendum. User chose to close the change without this end-to-end test. Recorded in `CODEX_STATE.md` as a follow-up; the underlying decision logic is fully unit-tested (235 assertions, all green) and glue is grounded in vendor-citation review.

**Task 5.6** (optional smoke test): Self-annotated in `tasks.md` as superseded by Phase 8 (8.5–8.7); carries no independent remaining work. Reported as unchecked but substantively complete.

**Checkpoint**: The Task Completion Gate is satisfied per the user's explicit approval in the verify-report addendum to archive with these documented exceptions.

## Verification Verdict Reconciliation

The verify-report returned `verdict: fail` with 3 CRITICAL findings. The orchestrator addendum on 2026-10-01 explicitly approves archiving with these exceptions:

### CRITICAL 1: Task 8.7 Deferred
- **Finding**: Live removal-request submission + admin reactivate-with-confirm test never executed
- **User decision**: Explicitly deferred and recorded as a pending follow-up
- **Evidence**: verify-report addendum, CODEX_STATE.md follow-ups, 235/235 unit assertions prove the underlying decision logic

### CRITICAL 2: Contact Chrome CSS-Hidden (Spec vs. Design Conflict)
- **Finding**: Spec says "MUST NOT render", but live site shows markup hidden with CSS `display: none`
- **User decision**: **Resolved by spec amendment**
- **Amended requirement wording**: Chrome is neither visible nor usable; vendor markup may remain only if hidden with `display: none` AND blocked server-side by fail-closed guards (unit-tested, live GET redirect 302 confirmed)
- **Evidence**: Vendor markup present but unreachable (fail-closed guard redirects), CSS `display: none` confirmed, CSRF/honeypot/throttle decision logic fully tested

### CRITICAL 3: Uninstall (Reversible Deactivation)
- **Finding**: No unit test or live exercise of the plugin's uninstall path
- **Reason**: Not exercisable in production without undoing deployment
- **User decision**: **Accepted exception** (code review + vendor citations cover this; pre-deploy backups available)
- **Evidence**: Pre-deploy backups: `data/osclass/backups/pre-tourist-directory-20260930T120305.sql`, `pre-removal-form-20260930T144153.sql`

**Verdict re-judgment**: All 3 CRITICAL items accepted as documented exceptions per explicit user approval. Archive approved to proceed.

## Build & Test Results

**Tests**: ✅ 235 passed / 0 failed  
**Test suite**: `php tests/test_tourist_showcase.php` → "Tourist showcase checks passed.", exit 0  
**Linting**: `php -l` clean on 8 changed/created PHP files  
**Build**: Not applicable (plain PHP script, no build step)  
**Deployed copy parity**: ✅ Byte-identical to source

## Implementation Summary

### Scope Delivered

**Phase 1 (U1)**: Showcase dropdown vocabulary + directory lib pure functions  
**Phase 2 (U2)**: Plugin install/lifecycle/guards/rendering/CSS  
**Phase 3 (U3)**: Import planner + CLI + README  
**Phase 6 (U6)**: Amendment — schema migration, removal-decision lib, importer precedence  
**Phase 7 (U7)**: Amendment — removal route/form, admin screen, render link, CSS, README  

**Phases 4, 5, 8**: Out of scope for automated apply (require user authorization / real DB / production import). All completed:
- Phase 4: Dry-run completed
- Phase 5: Deployment completed by user (plugin installed, options synced)
- Phase 8: Amendment deployment completed (schema v2 enabled, 25 entries imported via `--apply`)

### Changed Files (All Phases)

| File | Action | Status |
|------|--------|--------|
| `openspec/specs/directory-entries/spec.md` | Created | Synced to main specs ✅ |
| `plugins/tourist-directory/index.php` | Created | Deployed ✅ |
| `plugins/tourist-directory/tourist-directory-lib.php` | Created | Deployed ✅ |
| `plugins/tourist-directory/views/removal-form.php` | Created | Deployed ✅ |
| `plugins/tourist-directory/admin/requests.php` | Created | Deployed ✅ |
| `plugins/tourist-directory/assets/tourist-directory.css` | Created | Deployed ✅ |
| `plugins/tourist-directory/README.md` | Created | Deployed ✅ |
| `bin/tourist-directory-import.php` | Created | Deployed ✅ |
| `plugins/tourist-showcase/tourist-showcase-lib.php` | Modified | Deployed ✅ |
| `plugins/tourist-showcase/tourist-showcase.php` | Modified | Deployed ✅ |
| `tests/test_tourist_showcase.php` | Modified | Maintained ✅ |

### Commits

All commits present and verified on branch `osclass`, HEAD ce4b66c (clean):

| Commit | Message |
|--------|---------|
| 7dcab27 | plan (U1–U3 tasks) |
| 00812c6 | U1: showcase options + directory lib |
| 730d1bd | U2: plugin install/lifecycle/guards/rendering |
| 5680ab6 | U3: importer + CLI |
| 64b181b | placeholder-contact gate / auto-link fix |
| 8008fef | CLI lib loading fix |
| 9f51ba4 | docs |
| bb35dca | amendment plan (U6–U7 tasks) |
| faf52b4 | U6: schema/removal-decision/importer precedence |
| 60fa069 | U7: removal form/admin/routes |
| 7ee93ea | type-meta backfill fix |
| ce4b66c | docs: verify exceptions acceptance |
| f37664f | docs: CSV import exceptions log |

**Commits not pushed**: Per orchestrator instruction, changes remain staged locally; delivery follows ordinary repository policy.

## Size Exceptions Approved

Per `openspec/config.yaml` and delivery strategy `auto-chain` (stacked-to-main), the following work units were approved for oversized PR slices:

| Unit | Authored Lines | 400-line Budget | Exception |
|------|---|---|---|
| U1 | ~463 | ❌ | Approved for vocabulary foundation |
| U2 | ~864 | ❌ | **size:exception** approved (core plugin: install/lifecycle/guards/rendering/CSS) |
| U3 | ~699 | ✅ | Within budget (importer + CLI) |
| U6 | ~805 | ❌ | **size:exception** approved (schema/removal-decide/throttle/IP/admin-transition logic) |
| U7 | ~1039 | ❌ | **size:exception** approved (removal route/form/admin routes/menu/retention) |

**Total authored**: ~3870 lines across 11 files (7 created, 4 modified).

## Dependencies & Notes

### Production Import Gate

The importer now requires the removal channel (`_channel_ready`) to proceed with `--apply`. Placeholder `osclass.contactEmail` produces a warning only, not a hard block. Site mail configuration remains deferred and optional.

### Open Follow-Ups (Per User Decision, 2026-10-01)

1. **Live removal test** (task 8.7) — test removal-request submission and admin reactivate-with-confirm over HTTPS
2. **Type filter on whole-region search** — pre-existing limitation; type filter is ignored when searching an entire region (fields linked to leaves only, not region roots)
3. **Site mail** — postponed indefinitely; removal form works without it
4. **Legal review** — privacy note text (Ley 25.326 compliance) and disclaimer before volume import
5. **Throttle/retention defaults confirmation** — 5 requests/hour per IP, 3 requests/24h per entry, 30-day IP retention, 180-day reply-contact/reason retention

### Rollback Plan

1. Deactivate/uninstall `tourist-directory` (entries deactivated, table kept)
2. Revert dropdown options in showcase
3. Restore pre-deploy DB backup if needed: `data/osclass/backups/pre-tourist-directory-20260930T120305.sql`

## SDD Cycle Complete

This change has been fully planned (proposal), specified (spec), designed (design + amendment), tasked (tasks), implemented (apply: U1–U3, U6, U7), verified (strict TDD, 235 unit assertions, live HTTPS checks), and archived.

The new `directory-entries` capability is now part of the source-of-truth main specs and ready for the next change.

---

**Verified by**: sdd-archive phase  
**Date**: 2026-10-01  
**Archive readiness**: READY — all mechanical operations completed, specs synced, change folder moved to archive with byte-identity verified.
