# Design: Tourist Destination Categories

## Technical Approach

Evolve `tourist-identity` (explore Approach 3): a declarative region→destination tree (pure data file) plus pure plans in the lib; `index.php` only reads rows, executes plans, then `osc_update_cat_stats()` + `osc_cache_flush()`. Category 47 becomes region "Buenos Aires"; plugin-created ids live in pref `tourist_identity.category_map` (JSON `{key: id}`).

## Vendor Facts (app/osclass, read-only)

| Topic | Fact | Source |
|---|---|---|
| Insert | `insert($fields, $descByLocale)`: raw row insert, then per locale slug from `s_name`, uniquified `_1,_2` via `findBySlug` with **no self-exclusion** → identical es/en names give en slug `tandil_1` | `model/Category.php:900-921` |
| Slug lookup | `findBySlug` matches any locale (fallback query) | `Category.php:530-551,153-227` |
| Raw description insert | `insertDescription($row)` writes `s_slug` verbatim | `Category.php:931-935` |
| Model update | `updateByPrimaryKey` rewrites slugs and item `dt_expiration` (undefined `i_expiration_days` ⇒ 9999) → avoid | `Category.php:808-890` |
| Delete | `deleteByPrimaryKey` **recursively deletes subcategories and their items**, then plugin_category/description/stats/meta_categories rows; fires `delete_category` | `Category.php:774-796` |
| FKs | description→`t_locale` (en_US must be installed); `fk_i_parent_id` self-FK | `installer/struct.sql:223,235-236` |
| Plugin upgrade | No version-bump/update hook; only install, `_enable`, `_disable`, `_uninstall`, `_configure`; `_enable` re-runs only via market updater | `classes/Plugins.php:389-525`, `utils.php:3176-3264` |
| Stats | `osc_update_cat_stats()` rebuilds from `toTreeAll()` | `utils.php:2277-2296` |
| Item count | `Item::totalItems()` returns 0 on query failure (unsafe as a delete guard) | `model/Item.php:293-345` |

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|---|---|---|---|
| Data vs logic | Tree inline in lib / separate file | Separate file isolates ~70 data lines for review slicing | **`tourist-identity-tree.php`** (pure data), required by lib |
| Slugs | Vendor-generated both locales / explicit per node | Vendor gives `_1` en slugs for identical names | **Explicit `key` = slug** (ASCII, stable, also map key); insert with empty descriptions, then `insertDescription` per installed locale with same slug; collision → pure `-2,-3` resolver |
| 47 descriptions | `updateByPrimaryKey` / `updateName` / DAO update | Model call touches items/slugs; `updateName` lacks description/slug | **DAO update of `t_category_description`** (s_name, s_description, s_slug), insert if row missing |
| Identity of created rows | Match by slug / id map | Slugs editable by admin | **Persisted map**, written after each insert (crash-safe) |
| Non-tree rows | New logic / reuse | Reuse keeps prior tests meaningful | Tree plan delegates to existing `tourist_identity_category_plan($others, 0)` (all disabled) |
| Upgrade trigger | `_enable` hook / Re-apply | `_enable` mutates on any toggle | **Re-apply** (documented); every apply first ensures supplementary snapshot |
| Supplementary snapshot | Rewrite `snapshot` / new key | Main snapshot is write-once | **`tourist_identity.snapshot_tree`** `{version:"1.1.0", anchor:{id,i_position,descriptions}}`, captured only if absent, before any tree write |
| Uninstall delete | `deleteByPrimaryKey` / raw DAO | Vendor call cascades items | **Vendor call only when own count === 0 and no surviving children**, leaves before regions; unknown count (`null`) ⇒ disable |
| New row attributes | Defaults / copy anchor | Consistency | Copy 47's `i_expiration_days`, `b_price_enabled` |
| Re-apply feedback | Flash / inline | Flash invisible on configure | **Inline** via pure formatter, escaped |

## Data Flow

    apply (install | Re-apply):
      ensure snapshot (v1.0 main) -> ensure snapshot_tree (47 descs+position)
      -> delete seed items -> rows+descs+locales+map
      -> tree_plan -> inserts (regions, then leaves; map saved per insert)
         -> DAO updates (flags/parent/position) -> description writes
      -> currencies -> prefs -> logo -> showcase link(leaf_ids) -> stats -> flush
    uninstall:
      map+rows+counts -> uninstall_plan -> delete (leaves, regions) / disable
      -> save reduced map (or delete pref) -> prune showcase ids
      -> main snapshot restore (47 parent 4, flags) -> snapshot_tree restore
         (47 descs/slugs/position) -> currencies -> stats -> flush

## File Changes

| File | Action | Est. lines |
|---|---|---|
| `plugins/tourist-identity/tourist-identity-tree.php` | Create: `tourist_identity_tree()` | ~70 |
| `plugins/tourist-identity/tourist-identity-lib.php` | Modify: plans, version `1.1.0` | ~170 |
| `plugins/tourist-identity/index.php` | Modify: executors, snapshot_tree, uninstall, inline message, header 1.1.0 | ~150 |
| `tests/test_tourist_showcase.php` | Modify: RED-first assertions | ~200 |
| `plugins/tourist-identity/README.md` | Modify: tree, upgrade, rollback | ~40 |

~630 lines: exceeds 400. Suggested slices: (1) tree data + shape tests; (2) pure plans + tests; (3) wiring + README.

## Interfaces / Contracts

Pure (lib):

```php
tourist_identity_tree(): array // [['key'=>'buenos-aires','anchor'=>47,'names'=>['es_ES'=>..,'en_US'=>..],
                               //   'leaves'=>[['caba','Ciudad Autónoma…','Buenos Aires City'],…]],…]
tourist_identity_tree_plan(array $tree, array $rows, array $descs /*[id][locale]*/, array $map,
                           array $locales, array $anchorRow): array
  // ['insert'=>[['key','parent_key','position','fields','names'=>[loc=>name]]] regions first,
  //  'update'=>[id=>changes], 'describe'=>[id=>[loc=>['s_name','s_description'=>'','s_slug'=>key]]]]
  // map ids missing from rows are re-inserted; count(plan)=0 on second pass
tourist_identity_leaf_ids(array $tree, array $map): array        // tree order
tourist_identity_showcase_link_needed(array $selected, $target): bool // int|array, set compare
tourist_identity_resolve_slug(string $slug, callable $ownerOf, int $selfId): string
tourist_identity_build_tree_snapshot(array $anchorRow, array $anchorDescs): string
tourist_identity_tree_restore_plan(string $json): array          // throws on invalid JSON
tourist_identity_uninstall_plan(array $map, array $rows, array $counts /*id=>int|null*/): array
  // ['delete'=>ids (leaves first), 'disable'=>ids, 'map'=>kept]
tourist_identity_prune_ids(array $selected, array $removed): array
tourist_identity_reapply_message(int $n): string // "Re-apply complete: N change(s)."
```

Osclass-bound (`index.php`): `_read_descriptions($ids)` and `_item_count($id)` (DAO `COUNT(*)`, `false`⇒`null`), `_ensure_tree_snapshot()`, `_apply_tree()`, `_restore_tree()`.

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit | 6 regions ordered, 51 leaves, catch-all last, unique `^[a-z0-9-]+$` keys, both locales; plan from empty map, rerun = empty, deleted-row reinsert, 47 describe/position; defaults disabled; leaf ids = leaf set; link scalar (prior) + array; uninstall delete/disable/null/child-kept region; snapshot round-trip; slug resolver; message | RED first, `php tests/test_tourist_showcase.php`; all prior assertions kept |
| Lint | 3 PHP files | `php -l` |
| Integration/E2E | Deploy steps below | User-run, HTTPS `curl` |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary. Admin POST keeps `osc_csrf_check`.

## Migration / Rollout (user-run)

1. Tests + lint green. 2. Backup: `mysqldump --single-transaction --no-tablespaces <db> > data/backups/pre-destination-categories-<date>.sql` (own credentials; never printed). 3. `cp -r` + `diff -r` into `app/osclass/oc-content/plugins/tourist-identity/`. 4. Configure → Re-apply (inline count > 0). 5. Re-apply again → "0 change(s)". 6. HTTPS: home shows 6 regions in order; each region page lists leaves, catch-all last; publish form optgroups offer leaves only; showcase fields on a leaf; en_US check. Rollback: uninstall, or restore dump + redeploy 1.0.0.

## Open Questions

- [ ] Other installed locales (beyond es_ES/en_US) keep 47's old name; acceptable?
- [ ] Uninstall prunes showcase ids (not in proposal); spec should confirm.
