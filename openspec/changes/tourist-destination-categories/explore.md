# Exploration: tourist-destination-categories

> Mirror of Engram `sdd/tourist-destination-categories/explore` (observation 492). Orchestrator verified: `Field::findByCategory` filters on exact `mc.fk_i_category_id` (`Field.php:119-134`); live `osclass.selectable_parent_categories` is empty (off), so posting forces a leaf.

## Current State

- `tourist-identity` enforces a single top-level category: pure `tourist_identity_category_plan(rows, keepId=47)` enables 47 at root and disables every other row; applied by DAO update, then `osc_update_cat_stats()`.
- Snapshot records all pre-existing category rows; uninstall replays `b_enabled`/`fk_i_parent_id` for those rows only. There is no concept of plugin-created categories.
- Live: only 47 enabled at root; 94 defaults disabled; zero items; `tourist_showcase.category_ids = 47`.
- `tourist-showcase` links its 5 meta fields to any id list.

## Vendor facts

| Topic | Fact | Source |
| --- | --- | --- |
| Creation | `Category::insert($fields, $descriptionsByLocale)`; slug auto-uniquified against all categories, including disabled ones | `Category.php:900-921,530-551` |
| Ordering | `i_position ASC`; caller assigns positions | `Category.php:139,166` |
| Depth | Tree helpers recurse without a depth cap | `Category.php:277-367` |
| Field linkage | Exact category id, no parent→child inheritance | `Field.php:119-134,196-209` |
| Cache | Mutations need `osc_update_cat_stats()` + `osc_cache_flush()` | `Category.php` |
| Home | sigma home grid shows root categories only | `sigma/main.php:30-47` |
| Search sidebar | Root list, or the selected category's immediate children | `sigma/functions.php:669-691` |
| Posting | Parents render as non-selectable optgroups when `selectable_parent_categories` is off (live: off) | `Item.form.class.php:40-80` |
| Locations | Separate native model, currently empty; sigma shows an "All locations" widget | `sigma/main.php:55-60` |

## Approaches

1. Bolt tree logic onto the single-category constants — smallest diff, but two competing category authorities in one plugin.
2. New sibling plugin owning the tree — isolates code, but conflicts with tourist-identity re-disabling non-47 rows on every re-apply.
3. **Evolve tourist-identity's category plan into a declarative region→destination tree plan (recommended)** — one owner of category shape, reuses the pure plan / snapshot / restore idiom; requires re-verifying a shipped plugin.

## Recommendation

Approach 3:
- Create rows with `Category::insert()` (es_ES/en_US descriptions, explicit `i_position`), then `osc_update_cat_stats()` + `osc_cache_flush()`.
- Persist a created-id map (e.g. `tourist_identity.category_map`) for idempotency, since insert has no upsert.
- Repurpose category 47 in place as one region.
- Keep the 94 defaults disabled, not deleted.
- Link showcase fields to every leaf destination.
- Defer native Locations seeding.

## Affected Areas

- `plugins/tourist-identity/tourist-identity-lib.php`, `plugins/tourist-identity/index.php` (`apply_categories`, `restore_categories`, `apply_showcase_link`).
- `tests/test_tourist_showcase.php`.
- Specs: `portal-catalog-baseline` (MODIFIED), `identity-setup-lifecycle` (MODIFIED/ADDED); `portal-branding` likely untouched.

## Risks

- Regression on a shipped, archived plugin: all existing scenarios must still pass.
- Slug collisions with disabled defaults produce numbered slugs.
- Cache staleness without stats update + flush.
- Any leaf omitted from showcase linkage silently loses its fields.
- Uninstall semantics for plugin-created rows must be designed.

## Open Decisions

1. Final destination list; La Rioja placement (Norte vs Cuyo).
2. Category 47: repurpose as a region (recommended) vs retire.
3. 94 defaults: keep disabled (recommended) vs delete.
4. Showcase linkage: leaves only (recommended) vs also regions.
5. Locations: defer (recommended) vs seed now.
6. Destination ordering: alphabetical vs curated.
