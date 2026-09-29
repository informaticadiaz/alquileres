# Design: Tourist Portal Identity

## Technical Approach

New sibling plugin `tourist-identity` (Osclass 8.3.1 native lifecycle). Install hook snapshots prior state into preference section `tourist_identity`, then applies an idempotent plan; configure "Re-apply" re-runs it; uninstall hook restores the snapshot. Runtime hooks (`gettext`, `footer`, `init`) apply only when `OC_ADMIN === false`. All decisions (override map, category diff, currency filter, snapshot/restore plans) are pure functions in `tourist-identity-lib.php`; `index.php` only reads/writes Osclass state.

## Vendor Facts (verified in app/osclass)

| Topic | Fact | Source |
|---|---|---|
| Discovery | `Plugins::listAll()` lists only `<dir>/index.php`; names with dots (flat files) are skipped | `classes/Plugins.php:109-125` |
| Hook names | `addHook()` strips `osc_plugins_path()`; install hook `install_<dir>/index.php`, uninstall `<path>_uninstall`, configure `<path>_configure` | `Plugins.php:374-470,722` |
| gettext | Filter receives only the **translated** string (no key/domain) | `hTranslations.php:35-39` |
| Logo | `sigma_logo_url()` serves `osc_uploads_path().pref('logo','sigma')` if the file exists | `sigma/functions.php:214-219` |
| Currency | `b_enabled` is never read; `osc_get_currencies()` returns View key `currencies` if already exported | `hCurrency.php:57-95` |
| Category | Roots are `fk_i_parent_id IS NULL`; `updateByPrimaryKey()` rewrites slugs/expirations | `Category.php:381,808-890` |
| Items | `ItemActions(true)->delete($secret,$id)` logs, deletes resources/meta/stats, fires hooks | `ItemActions.php:1370`, `Item.php:1377` |
| Cache | Keys are locale-suffixed; admin bypasses reads | `hCache.php:77-99` |

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|---|---|---|---|
| Deploy layout | Flat files (like showcase) / subfolder | Flat is invisible to `listAll()` (showcase only works via direct install URL); subfolder is native and holds assets | **Subfolder** `plugins/tourist-identity/index.php` |
| String overrides | Key/domain-aware / translated-output map per locale | Filter has no key/domain | **Map `locale => [translated => replacement]`**; avoid collision-prone strings ("Todas las categorías", "Buscar") |
| ARS-only form | Delete USD/EUR/GBP / flip `b_enabled` only / flip + public View export | Delete breaks spec + rollback; flag alone is inert | **Flip `b_enabled` + `init` hook exporting enabled-only `currencies`**; `currencies_` stays full so prices still format |
| Category update | `updateByPrimaryKey` / DAO `update` on `t_category` | Model call regenerates slugs and item expirations | **DAO update** of `b_enabled`, `fk_i_parent_id` only; then `osc_update_cat_stats()` |
| Logo | PNG (needs converter/binary) / SVG | No install allowed; `<img>` accepts SVG | **SVG** copied to uploads as `tourist_identity_logo.svg`; `sigma_logo.png` untouched |
| Snapshot | JSON pref / file | Pref is DB-local, LONGTEXT | **JSON in `tourist_identity.snapshot`**, written once, never overwritten |
| Showcase link | Own SQL / call `tourist_showcase_save_categories(array(47))` | Reuse keeps one owner of field links | **Call if `function_exists`**; rollback leaves showcase untouched |

## Data Flow

    install/re-apply (admin)
      read state -> [snapshot exists?] no -> persist snapshot
      -> delete items 1,2 (skip absent) -> category diff -> DAO update -> cat stats
      -> ensure ARS, currency=ARS, flags -> prefs (core, sigma) -> copy logo
      -> ensure showcase 47 -> applied_version -> osc_cache_flush()
    uninstall: snapshot -> restore plan -> prefs/categories/currency flags,
      ARS b_enabled=0 -> delete our logo -> delete section -> flush
    public request: init(currency View) | gettext(map) | footer(disclaimer)

## File Changes

| File | Action | Description |
|---|---|---|
| `plugins/tourist-identity/index.php` | Create | Header, hooks, Osclass-bound apply/restore/configure |
| `plugins/tourist-identity/tourist-identity-lib.php` | Create | Pure contracts |
| `plugins/tourist-identity/assets/logo.svg` | Create | Logo source and installed asset |
| `plugins/tourist-identity/README.md` | Create | Spanish ops notes, source-string list |
| `tests/test_tourist_showcase.php` | Modify | Require identity lib; new assertions |

## Interfaces / Contracts

Pure (lib, tested):

```php
tourist_identity_version(): string
tourist_identity_target_prefs(): array            // [[section,name,value],...]
tourist_identity_override_map(): array            // ['es_ES'=>[from=>to], 'en_US'=>[...]]
tourist_identity_override_text(string $text, string $locale, bool $isAdmin, array $map): string
tourist_identity_category_plan(array $rows, int $keepId): array // [id=>['b_enabled'=>..,'fk_i_parent_id'=>..]] changed rows only
tourist_identity_enabled_currencies(array $rows): array
tourist_identity_currency_plan(array $rows, string $target): array
tourist_identity_build_snapshot(array $prefs, array $categories, array $currencies): string // JSON; absent pref = null
tourist_identity_restore_plan(string $json): array  // throws on invalid JSON
tourist_identity_disclaimer(string $locale): string
tourist_identity_configure_url(string $admin, string $file, string $pluginsPath): string
```

Initial map: es_ES `¿Qué estás buscando hoy?`→`Encontrá tu alojamiento temporario en Argentina`, `Publicar anuncio`→`Publicar alojamiento`, `Últimos anuncios`→`Últimos alojamientos`; en_US `What are you looking for today?`, `Publish Ad`, `Latest Listings`, plus the Spanish placeholder→`Search by city, province or property type`.

Osclass-bound (index.php): `tourist_identity_install()`, `_apply()` (returns change count), `_uninstall()`, `_configure()` (CSRF-checked `reapply`), `_gettext($s)`, `_footer()`, `_init_currencies()`.

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit | Every lib contract, including admin guard, idempotent empty plan on second pass, snapshot round-trip, null-vs-empty prefs | RED first in `php tests/test_tourist_showcase.php` |
| Lint | Plugin files | `php -l` |
| Integration | Install, re-apply (0 changes), uninstall | Admin UI after re-sync |
| E2E | Title, H1, CTA, placeholder, logo URL, disclaimer, no "Powered by", one category, ARS-only form, items 1/2 = 404; en_US locale; admin untouched | `curl` over `https://alquileres.diazignacio.ar` |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary. The single admin POST reuses Osclass CSRF (`osc_csrf_check`).

## Migration / Rollout

1. Tests + lint pass. 2. Copy `plugins/tourist-identity/` to `app/osclass/oc-content/plugins/tourist-identity/` (and compare with `diff -r`). 3. Install from Plugins. 4. HTTPS checks. 5. Re-apply shows 0 changes. Rollback = uninstall; item deletion is irreversible (accepted).

## Open Questions

- [ ] Built-in PHP server must serve `.svg` as `image/svg+xml`; verify in HTTPS checks.
- [ ] Posted non-ARS currency is still accepted server-side (form-only restriction).
- [ ] Rewrite URLs of 47 lose the parent segment if rewrite is enabled; nothing indexed yet.
