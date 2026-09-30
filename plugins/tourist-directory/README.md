# Tourist Directory Entries

Osclass plugin for "directory entries": admin/importer-created listings for commercial tourist
complexes, shown alongside owner listings but factual-only (no contact, no price, no copied
content), with a visible public-directory label and a removal path. See
`openspec/changes/tourist-directory-entries/specs/directory-entries/spec.md` for the full
requirements and `openspec/changes/tourist-directory-entries/design.md` for the architecture.

## Files

- `tourist-directory-lib.php` — pure, standalone-loadable decision functions (validation, mapping,
  description generation, fingerprinting, the fail-closed guard decision, item Params building).
  Unit-tested by `tests/test_tourist_showcase.php`; never touches the database, Osclass hooks, or
  the filesystem.
- `index.php` — Osclass glue: install/uninstall/enable/disable lifecycle, the fail-closed contact
  guards, rendering hooks, and create/update/retire/reactivate. Requires `ABS_PATH`.
- `assets/tourist-directory.css` — front-end-only stylesheet; cosmetic, not the enforcement layer.

## Importer CLI

`bin/tourist-directory-import.php` (repo root, **never** synced under `app/osclass/oc-content/
plugins/` — it is a standalone operator tool, not a plugin) drives `tourist_directory_create()` /
`tourist_directory_update()` / `tourist_directory_retire()` / `tourist_directory_reactivate()`
against a seed CSV. See `esquema-seed-complejos.md` for the seed's full column definitions and
`openspec/changes/tourist-directory-entries/design.md`'s "Seed Contract" section for the exact
create/update/retire/skip rules.

```
php bin/tourist-directory-import.php --file=<path> [--osclass-root=<path>] [--apply]
  [--allow-placeholder-contact] [--allow-reactivate]
```

- `--file=<path>` (required) — the seed CSV.
- `--osclass-root=<path>` (default `app/osclass`) — the target Osclass installation root (the
  directory holding `config.php` and `oc-load.php`).
- `--apply` — writes changes. Without it, the importer only prints the plan and never touches the
  database (dry run is the default).
- `--allow-placeholder-contact` — overrides the production mail gate below. Only for a non-production
  install.
- `--allow-reactivate` — lets a previously retired entry whose seed row is back to `candidato` be
  reactivated. Without it, a retired entry always stays retired, even if its row changes back.

**Seed columns read**: only `id`, `estado_catalogo`, `nombre`, `localidad`, `destino`, `tipo`, `web`
(`tourist_directory_seed_columns()`). Every other column — `contacto`, `notas`, `fuente`,
`estado_prospecto`, `fecha_ultimo_contacto`, ... — is dropped by `tourist_directory_parse_rows()`
before any row is validated, planned, or printed; none of them are ever written to Osclass, logged,
or shown in the report.

**Per-row outcome** (`tourist_directory_plan()`, unit tested): a `candidato` row with a valid `web`
creates a new entry when none exists, updates the existing entry only when its public-field
fingerprint changed, and does nothing when it matches. A `baja`/`anuncio_propio` row retires
(deactivates, never deletes) an existing active entry; it is a no-op if the entry is already retired
or does not exist. Every other `estado_catalogo` is skipped. A marker whose underlying item no longer
exists is reported and retired (`missing_item`) and is never recreated. A seed id present as an
existing marker but absent from the current file is only reported (`not_in_seed`), never retired by
absence alone. Invalid rows (duplicate id, unknown `destino`/`tipo`, unsafe URL, missing/too-long
title) are skipped and reported as validation errors.

**Production mail gate**: with `--apply`, the importer refuses to run while `osclass.contactEmail` is
still a placeholder (`.invalid`/`.test`/`.example`/`.localhost`, bare `localhost`, or `example.*`),
unless `--allow-placeholder-contact` is passed. A dry run is never gated — it never writes, regardless
of mail configuration. The importer also refuses entirely (dry run or `--apply`) when the
`tourist-directory` plugin is not installed/active on the target Osclass site.

**Auto-link guard**: before any `--apply` write, the importer refuses if a registered Osclass user
already owns this install's per-plugin placeholder contact address. `ItemActions::prepareData()`
auto-links an admin-created/edited item to a matching user by email (`ItemActions.php:1785`) and would
silently replace the placeholder `contactName`/`contactEmail` with that user's real identity —
checked once, globally, before any row is written.

No email is ever sent by an importer run: `ItemActions::add()` only calls `sendEmails()` for a
non-admin submission (`ItemActions.php:311-313`), and `ItemActions::edit()` never calls it at all.

## Install / lifecycle

Install creates the `t_directory_entry` marker table (`CREATE TABLE IF NOT EXISTS`, so a reinstall
is safe) and, on first install only, generates a per-install placeholder contact address
(`directorio-<16 hex>@directorio.invalid`, stored as the `tourist_directory.contact_email`
preference). It also syncs the showcase accommodation-type dropdown options
(`tourist_showcase_sync_options()`), so a directory-only install still gets the current vocabulary.

- **Enable** reactivates every managed item whose marker is not retired (`dt_retired IS NULL`).
- **Disable** deactivates every managed item, retired or not.
- **Uninstall** deactivates every managed item. The marker table and the placeholder-email
  preference are never dropped, so a later reinstall re-links the same items instead of orphaning
  them.

No underlying item is ever deleted by this plugin. Retiring an entry deactivates its item and stamps
`dt_retired`/`s_retired_reason`; reactivating requires an explicit, separately-gated caller
(`ctx['allow_reactivate']` — the future CLI's `--allow-reactivate` flag).

## Guard behavior

Every directory entry's contact form, send-to-friend form, and comment form are unreachable, even
for a forged direct POST carrying a valid CSRF token:

- `init_item` blocks early for the `contact`, `contact_post`, `send_friend`, `send_friend_post`, and
  `add_comment` actions, before Osclass's own action switch runs.
- `pre_item_contact_post`, `pre_item_send_friend_post`, and `pre_item_add_comment_post` block again,
  after Osclass's own `osc_csrf_check()` already ran for that action.

Both call sites share `tourist_directory_guard()`, which resolves the item id from
`Params::getParam('id')` and, when available, cross-checks it against the hook-provided item row's
`pk_i_id`; a mismatch or a missing/non-positive id blocks. The actual entry lookup
(`tourist_directory_lookup_entry()`) is a fresh, uncached `SELECT` against `t_directory_entry` every
time — it returns `true` (confirmed entry), `false` (confirmed non-entry: the query ran and found no
row), or `null` (the query failed or threw). `tourist_directory_should_block()` (in the lib, unit
tested) fails closed: only an explicit `false` lets a request through; `true`, `null`, or any other
value blocks. A `false` result whose item's `s_contact_email` still equals the placeholder address
also blocks (an orphaned entry whose marker row is gone).

A blocked request gets a localized flash message and is redirected to the item's own page (or home,
if the id could not be resolved at all) — that redirect exits immediately, so nothing after it runs.

Owner listings are entirely unaffected: the guard only blocks when the lookup returns something
other than `false`.

## Rendering

An entry's detail page shows a public-directory notice near the title (label, "Visitar sitio
oficial" link to the official website, "Solicitar baja" removal link), a short repeated notice at
the top of the sidebar, no price, and no contact/send-friend/comment UI. Listing cards (category,
search, home) show a small "Ficha de directorio" badge next to the title and a `tourist-directory-
card` class on the card for the stylesheet to key off. Structured data (`Product` JSON-LD, Open
Graph/Twitter price and rating tags) is suppressed on an entry's own page only.

"Solicitar baja" links to the site's general contact form (`osc_contact_url()`, not the guarded item
contact form) with a `tourist_directory_removal=<id>` marker that pre-fills the subject field.

## CSS scope

`assets/tourist-directory.css` is enqueued on the `header` hook, front-end only (skipped when
`OC_ADMIN` is true). It is cosmetic: it hides the still-rendered-but-unreachable contact/comment
markup and the now-empty price box on an entry's own page (scoped by the `tourist-directory-entry`
body class), and the empty currency span on listing cards (scoped by the `tourist-directory-card`
card class). The guards, not the CSS, are what actually stops a request from going through.
