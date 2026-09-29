# Apply Progress: tourist-portal-identity

> File mirror of Engram `sdd/tourist-portal-identity/apply-progress` (observation 485), plus orchestrator fixes (observation 486) and deployment evidence (observation 488).

Mode: Strict TDD. Status: 24/24 tasks closed (4.3 skipped by user decision).

## Work units

| Unit | Scope | Commit | Ledger |
| --- | --- | --- | --- |
| 1 | Phase 1 — pure lib contracts (`tourist-identity-lib.php`), RED→GREEN | `96a423f` | settled `complete` |
| 2 | Phases 2–3 — Osclass wiring (`index.php`, logo, README), local verification | `afbe184` | settled `complete` |
| 3 | Phase 4 — gated deployment | performed by the user (auto-mode classifier blocked the agent) | not acquired |

## Pure contracts (all unit-tested in `tests/test_tourist_showcase.php`)

`tourist_identity_version`, `target_prefs`, `snapshot_pref_keys`, `override_map`, `override_text` (admin guard as parameter), `category_plan`, `enabled_currencies`, `currency_plan`, `pref_plan`, `build_snapshot`, `restore_plan`, `showcase_link_needed`, `disclaimer`, `configure_url`.

## Orchestrator corrections (each RED→GREEN)

1. Core preference section `core` → `osclass` (`osc_set_preference` default section, `hPreference.php:1803`). Without it the title, description and currency would not have changed.
2. `sigma.logo` added to snapshot keys; uninstall removes the plugin logo file after restoring the original preference. Without it, uninstall left the site without a logo.
3. Showcase linkage decision extracted to `tourist_identity_showcase_link_needed()` with integer-normalized comparison (closes verify WARNING 1).

## Local verification (Phase 3)

- `php tests/test_tourist_showcase.php` exit 0; `php -l` clean on both plugin files.
- Idempotency hand trace: second `apply()` returns 0 across items, categories, currencies, prefs, logo and showcase link (each sub-plan unit-tested for an empty second pass).

## Deployment (Phase 4)

- 4.1 Copy to `app/osclass/oc-content/plugins/tourist-identity/`, `diff -r` identical.
- 4.2 Installed by the user from oc-admin. Pre-deploy backup: `data/osclass/backups/pre-tourist-identity-20260929T104338.sql`.
- 4.3 Skipped by user decision: the configure screen renders without the admin layout, so the result flash is not visible.
- 4.4 Verified read-only (DB + HTTPS): title, meta description, es_ES/en_US H1, CTA, placeholder, SVG logo (`image/svg+xml`), footer disclaimer, no "Powered by Osclass", single enabled category 47 at root (94 disabled), ARS-only (form renders hidden `currency=ARS`), items 1 and 2 return HTTP 410, admin login free of public overrides, snapshot stored in `tourist_identity.snapshot`.

## Known follow-ups

- Configure screen does not show the re-apply result.
- "Publicar un anuncio" page title is not overridden.
- Footer disclaimer needs a light legal review before real traffic.
