# Proposal: Tourist Destination Categories

## Intent

The portal has one category ("Alquiler Vacacional", id 47), so visitors cannot browse by where they want to stay. Replace it with the confirmed region → destination tree (6 regions, 51 leaves, `research.md`), owned and reversible by `tourist-identity`.

## Scope

### In Scope
- Declarative tree in the lib: 6 regions (Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia), 51 leaves in `research.md` order, catch-all last; es_ES/en_US names verbatim.
- Category 47 repurposed in place as **Buenos Aires** (first region; keeps id, stays root); its original descriptions are snapshotted for restore.
- New rows via `Category::insert()` with explicit `i_position`; idempotency through a persisted created-id map (`tourist_identity.category_map`).
- 94 defaults stay disabled, not deleted.
- Showcase fields linked to all 51 leaves (exact ids).
- Uninstall: restore 47 (descriptions, parent 4, flag); delete plugin-created rows with zero items; disable rows holding items and keep their ids in the map for reuse.
- Cheap fix: configure screen prints the re-apply change count inline (flash is invisible there).
- Version bump; gated user-run deployment with pre-deploy DB backup.

### Out of Scope
- Native Osclass Locations; accommodation type as categories (stays a showcase filter); theme changes; Costa Atlántica per-town split.

## Capabilities

### New Capabilities
- None.

### Modified Capabilities
- `portal-catalog-baseline`: "Single Top-Level Category" becomes a destination tree requirement; "Category Linkage Preserved" links all leaves.
- `identity-setup-lifecycle`: MODIFIED Idempotent Apply, Snapshot on Install (descriptions of 47, supplementary snapshot for already-installed sites), Restore on Uninstall (created-row handling), HTTPS-Verifiable Success; ADDED created-category tracking and visible re-apply result.

`portal-branding`: untouched.

## Approach

Explore Approach 3. Pure lib functions: tree definition, tree plan (inserts/updates from current rows plus map), leaf-id list, link check, restore plan. `index.php` only executes plans, then `osc_update_cat_stats()` + `osc_cache_flush()`. Strict TDD in `tests/test_tourist_showcase.php`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `plugins/tourist-identity/tourist-identity-lib.php` | Modified | Tree data and plans |
| `plugins/tourist-identity/index.php` | Modified | Apply/restore/link, inline result |
| `plugins/tourist-identity/README.md` | Modified | Tree and rollback |
| `tests/test_tourist_showcase.php` | Modified | New and regression assertions |
| DB `oc_t_category*`, `oc_t_meta_categories` | Modified | Via plugin code only |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Live snapshot lacks 47 descriptions | High | Capture supplementary snapshot before first tree apply |
| Vendor category delete cascades to items | Med | Design verifies; delete only zero-item rows |
| Slug collisions with disabled defaults | Med | Accept numbered slugs; HTTPS check |
| Omitted leaf loses fields | Low | Unit test: link set equals leaf set |
| Diff exceeds 800-line budget | Med | Tasks may chain lib/wiring slices |

## Rollback Plan

- Uninstall per scope; snapshot restores 47 and defaults.
- Fallback: restore the pre-deploy SQL backup and redeploy plugin 1.0.0.

## Dependencies

- tourist-identity 1.0.0 and tourist-showcase active; user-run deployment.

## Success Criteria

- [ ] `php tests/test_tourist_showcase.php` passes, including all prior assertions.
- [ ] HTTPS home lists exactly the 6 regions in order.
- [ ] Each region page lists its destinations, catch-all last.
- [ ] Publish form offers only leaves, grouped by region.
- [ ] Showcase fields appear for a leaf.
- [ ] Second re-apply shows "0 change(s)" on screen.
