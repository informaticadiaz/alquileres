# Exploration: tourist-portal-identity

> Mirror of Engram topic `sdd/tourist-portal-identity/explore` (observation 477). Orchestrator verified: `__()` applies `osc_apply_filter('gettext', ...)` (`oc-includes/osclass/helpers/hTranslations.php:35-39`); sigma reads `keyword_placeholder` (`header.php:106`, default in `functions.php:50-51`) and `footer_link` (`footer.php:52`). Live currency preference is `USD`.

## Current State

- Osclass 8.3.1 + theme `sigma` (vendor, git-ignored under `app/osclass`). Own code: `plugins/tourist-showcase/` (source), deployed flat to `app/osclass/oc-content/plugins/` (re-sync required; never edit deployed copy).
- Site prefs: `pageTitle` = "Osclass local", `pageDesc` empty, `contactEmail` = `admin@localhost.invalid` (mail unconfigured), `currency` = `USD`, public locale `es_ES`, admin locale `en_US`.
- Sigma prefs: `logo` = `sigma_logo.png`, `keyword_placeholder` = "ie. PHP Programmer", `footer_link` = 1 ("Powered by Osclass"), `defaultShowAs` = list.
- Categories: default 95-node Osclass tree (For sale, Vehicles, Classes, Real estate, Services, Community, Personals incl. dating subcategories, Jobs), all enabled, es_ES translated. Category 47 "Vacation Rentals / Alquiler Vacacional" (child of 4) is the only category linked to the plugin's 5 meta fields via `tourist_showcase.category_ids = "47"`.
- Items: 1 = default "Example Ad" (category 9); 2 = test cabin (category 47).

### Branding sources in sigma

- Logo: theme pref `logo`, uploaded via Admin > Appearance > Header logo.
- Search placeholder: theme pref `keyword_placeholder` (Admin > Appearance > Theme settings); value is re-passed through `__()`.
- Hero H1 "What are you looking for today?", "All categories", "Latest Listings", "All locations", "Publish Ad", etc.: hardcoded `_e()`/`__()` with domain `sigma` in `header.php`, `main.php`, `functions.php`, from `themes/sigma/languages/{locale}/theme.{po,mo}`.
- Footer credit: gated by `footer_link`.
- Osclass has no child-theme mechanism.

### Key mechanisms

- `gettext` filter: a plugin can register `osc_add_filter('gettext', ...)` to rewrite any rendered string (theme or core) without touching vendor files. Exact-string match; must not leak into admin strings.
- Category `b_enabled`: all public finders (`listEnabled`, `findRootCategoriesEnabled`, `findSubcategoriesEnabled`) filter on it; disabling hides without deleting rows or items (reversible). `Category::insert()` allows programmatic creation.
- Plugin coupling: `tourist_showcase_save_categories()` links the 5 meta fields and stores `tourist_showcase.category_ids`; any taxonomy change must re-run it.
- Reproducibility: DB state can be applied idempotently from a plugin install/activate hook, extending the pattern of `tourist_showcase_install()`.

## Affected Areas

- `plugins/tourist-showcase/tourist-showcase.php` / `tourist-showcase-lib.php` (or a new sibling plugin, e.g. `plugins/tourist-identity/`).
- `tests/test_tourist_showcase.php` (strict TDD for new pure logic).
- Osclass DB state: category flags/parents, `oc_t_preference` rows (core, `sigma`, `tourist_showcase`) — via idempotent hook code, not ad-hoc SQL.
- `app/osclass/oc-content/themes/sigma/*` — read-only reference.
- Admin-only manual steps (logo upload) where no safe code path exists without shipping a binary.

## Approaches

1. **Preferences-only (admin UI, no code)** — Low effort, reversible; not reproducible from the repo, leaves generic wording. Not SDD-worthy.
2. **Plugin-driven idempotent identity setup (recommended)** — install/activate hook sets core title/description/currency, sigma prefs, category enabled/parent state, and a `gettext` override map (es_ES/en_US); reuses category 47. Reproducible, versioned, testable; `gettext` overrides are upgrade-fragile; logo would add a binary. Medium effort.
3. **Full taxonomy rebuild + theme replacement** — strongest transformation; touches ~95 rows, duplicates the plugin's "type" field concept, adds unverified vendor surface. High effort and risk.

## Recommendation

Approach 2, reusing category 47. Category-restructure depth is an explicit product decision; a full rebuild can be a later change.

## Risks

- `gettext` overrides are exact-match and break silently on vendor upgrades; keep a documented source-string list and re-check after upgrades.
- Disabling generic branches orphans item 1 unless it is deleted or recategorized.
- Contact email placeholder and unconfigured mail: identity work does not make enquiries work end-to-end.
- Re-parenting 47 must be verified against the plugin's ID-based search filters during design.

## Open Product Decisions

1. Category strategy: minimal disable (keep 47 under Real estate) / re-parent 47 to root / full multi-category restructure.
2. Site title, tagline and meta description wording (es_ES primary, en_US fallback).
3. Which hardcoded public strings to override and their wording.
4. Currency: ARS, USD or both.
5. Real contact email: in scope or separate follow-up.
6. Logo: new artwork or keep placeholder.
7. Test items 1 and 2: clean up in this change or separately.
