# Proposal: Tourist Portal Identity

## Intent

The public site still reads as a generic Osclass install ("Osclass local", 95 generic categories, USD, sigma placeholders, test ads). Visitors must immediately see an Argentine short-term-rental showcase, reproducibly from the repo.

## Scope

### In Scope
- New sibling plugin `plugins/tourist-identity/` with idempotent, versioned setup plus snapshot-based rollback.
- Core prefs: `pageTitle` = "Alquileres Temporarios"; `pageDesc` = research meta description (es_ES).
- Categories: `b_enabled=0` for every category except 47; 47 re-parented to root; `tourist_showcase.category_ids` stays `47`.
- Currency: insert ARS, set default `currency=ARS`, disable USD/EUR/GBP (`b_enabled=0`).
- Public-only `gettext` override map (es_ES + en_US): hero H1 "Encontrá tu alojamiento temporario en Argentina", "Publicar alojamiento", related sigma strings.
- Sigma prefs: `keyword_placeholder` = "Buscá por ciudad, provincia o tipo de alojamiento"; `footer_link=0`; `logo` = new asset.
- Footer disclaimer via `footer` hook (research copy).
- Logo: SVG source + PNG in the plugin, installed programmatically.
- Delete test items 1 and 2 through the native Item manager.

### Out of Scope
- Real contact email, mail setup, bookings/availability/payments, new theme, provincial legal review, taxonomy rebuild.

## Capabilities

### New Capabilities
- `portal-branding`: title, description, logo, public copy overrides, footer disclaimer, placeholder.
- `portal-catalog-baseline`: single root category, ARS-only currency, test-data removal.
- `identity-setup-lifecycle`: idempotent apply, snapshot, rollback, re-sync.

### Modified Capabilities
- None.

## Approach

Explore Approach 2. Sibling plugin rationale: `tourist_showcase_install()` resets `category_ids` to `''`; separation keeps showcase single-purpose, gives identity its own install/uninstall lifecycle, and lets rollback run without touching showcase metadata. Install snapshots prior values into section `tourist_identity`, then applies; a configure "Re-apply" action re-runs idempotently. Pure logic (override map, category plan, snapshot diff) lives in a lib file tested by the strict TDD script. Vendor untouched; deployed copy re-synced from source.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `plugins/tourist-identity/` | New | Plugin, lib, assets (SVG/PNG), README |
| `tests/test_tourist_showcase.php` | Modified | Pure-logic assertions |
| DB: `oc_t_category`, `oc_t_currency`, `oc_t_preference`, `oc_t_item` | Modified | Via plugin code only |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `gettext` exact-match breaks on sigma upgrade | Med | Documented source-string list; re-check after upgrades |
| Override leaks into admin | Low | Guard on `OC_ADMIN`; test |
| Re-parenting 47 alters URLs/search | Med | Verify in design; HTTPS checks |
| Disclaimer lacks legal review | Med | Record as pending sanity check (CCyC 1199, Ley 24.240) |
| Showcase reinstall resets `category_ids` | Low | Identity re-apply restores `47` |

## Rollback Plan

- Uninstall restores snapshot: prefs, category flags, parent of 47, currency flags/default, logo.
- ARS row kept disabled (harmless).
- Item deletion is irreversible; accepted (test data).

## Dependencies

- tourist-showcase active with fields linked to 47.

## Success Criteria

- [ ] `php tests/test_tourist_showcase.php` passes.
- [ ] HTTPS home shows title, H1, placeholder, CTA, logo, disclaimer; no "Powered by Osclass".
- [ ] Only "Alquiler Vacacional" listed; old category URLs return no public listing.
- [ ] Publish form offers only ARS.
- [ ] Items 1/2 return 404; admin strings unchanged; re-apply is a no-op.
