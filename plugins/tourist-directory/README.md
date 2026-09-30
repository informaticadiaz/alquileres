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

The importer CLI (`bin/tourist-directory-import.php`) that drives `tourist_directory_create()` /
`tourist_directory_update()` / `tourist_directory_retire()` / `tourist_directory_reactivate()` is a
separate, later change; this plugin exposes those functions but does not wire a command to them.

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
