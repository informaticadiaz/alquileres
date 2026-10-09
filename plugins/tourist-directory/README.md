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
- `index.php` — Osclass glue: install/uninstall/enable/disable lifecycle (including the schema
  migration), the fail-closed contact guards, rendering hooks, create/update/retire/reactivate, the
  two routes, and the public/admin removal-request POST handlers. Requires `ABS_PATH`.
- `views/removal-form.php` — the public removal request form (`tourist-directory-removal` route).
- `admin/requests.php` — the admin removal-requests screen (`tourist-directory-admin` route).
- `assets/tourist-directory.css` — front-end-only stylesheet, plus the removal-form and admin-screen
  rules; cosmetic, not the enforcement layer.

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

**Production import gate**: with `--apply`, the importer refuses to run unless the removal request
channel is ready (`tourist_directory.schema_version >= 2` and the request table probes OK — see
"Removal request channel" below). A dry run is never gated — it never writes, regardless of channel
state. A placeholder `osclass.contactEmail` (`.invalid`/`.test`/`.example`/`.localhost`, bare
`localhost`, or `example.*`) only produces a non-blocking warning in the report;
`--allow-placeholder-contact` is still parsed but is now a deprecated no-op — the old hard gate on
that value is gone. The importer also refuses entirely (dry run or `--apply`) when the
`tourist-directory` plugin is not installed/active on the target Osclass site, or when its per-install
directory `contactEmail` preference itself is missing.

**Auto-link guard**: before any `--apply` write, the importer refuses if a registered Osclass user
already owns this install's per-plugin placeholder contact address. `ItemActions::prepareData()`
auto-links an admin-created/edited item to a matching user by email (`ItemActions.php:1785`) and would
silently replace the placeholder `contactName`/`contactEmail` with that user's real identity —
checked once, globally, before any row is written.

No email is ever sent by an importer run: `ItemActions::add()` only calls `sendEmails()` for a
non-admin submission (`ItemActions.php:311-313`), and `ItemActions::edit()` never calls it at all.

## Install / lifecycle

Install runs the full schema migration (see "Removal request channel" below — both the marker table
and, since the Amendment, the removal-request table) and, on first install only, generates a
per-install placeholder contact address (`directorio-<16 hex>@directorio.invalid`, stored as the
`tourist_directory.contact_email` preference). It also syncs the showcase accommodation-type dropdown
options (`tourist_showcase_sync_options()`), so a directory-only install still gets the current
vocabulary. **Enable** re-runs the same migration, so **Disable then Enable** is how an already-
installed site picks up a pending schema step after the deployed plugin files are updated.

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
search, home) show a "Ficha informativa" badge and a `tourist-directory-card` class. Other public
listings receive a distinct "Publicado por el alojamiento" badge and a `tourist-owner-card` class;
their detail page identifies them as managed by the property. Structured data (`Product` JSON-LD,
Open Graph/Twitter price and rating tags) is suppressed on an entry's own page only.

"Solicitar baja" links to `osc_route_url('tourist-directory-removal', ['entry' => $id])` — the
plugin's own route-based removal form (see "Removal request channel" below), omitted from the
markup only when `osc_route_url()` returns `''` (the route was never registered, i.e. the plugin is
disabled). Mail is not involved at any point.

## CSS scope

`assets/tourist-directory.css` is enqueued on the `header` hook, front-end only (skipped when
`OC_ADMIN` is true). It is cosmetic: it hides the still-rendered-but-unreachable contact/comment
markup and the now-empty price box on an entry's own page (scoped by the `tourist-directory-entry`
body class), and the empty currency span on listing cards (scoped by the `tourist-directory-card`
card class). The guards, not the CSS, are what actually stops a request from going through. The same
file also carries the removal-form and admin-screen rules (honeypot off-screen positioning, form
spacing, the admin table and inline per-row action forms) — none of those are enqueued in `oc-admin`.

## Removal request channel (Amendment)

Site mail configuration is postponed indefinitely. Removal requests are handled entirely through a
plugin-owned channel that works without any email being sent.

### Schema migration

`tourist_directory.schema_version` tracks two idempotent steps: 1) the `t_directory_entry` marker
table (present since the original install), 2) the new `t_directory_removal_request` table. Both use
`CREATE TABLE IF NOT EXISTS`, so re-running is always safe. `tourist_directory_ensure_schema()` runs
from both **install** and **enable** — after upgrading the deployed plugin copy, **Disable then
Enable** from oc-admin (not just leaving it enabled) is what actually runs a pending migration step.
The same routine also generates the per-install `tourist_directory.ip_salt` preference once (32
random bytes, hex; never rotated, since rotating it would make every stored `s_ip_hash` unrecognizable
for throttle lookups).

The removal channel is "ready" only once `schema_version >= 2` **and** a live
`SELECT 1 FROM t_directory_removal_request LIMIT 1` probe succeeds. Until then, the public form shows
a "temporarily unavailable" message and writes nothing, and the CLI importer refuses `--apply` (a dry
run is still allowed and only warns).

### Routes

| Route id | Pattern | File |
|---|---|---|
| `tourist-directory-removal` | `directorio/solicitar-baja/([0-9]+)` (pretty: `directorio/solicitar-baja/{entry}/`) | `views/removal-form.php` |
| `tourist-directory-admin` | `tourist-directory-admin/?` | `admin/requests.php` |

Both are registered at plugin load (`index.php`, only while the plugin is enabled), before
`Rewrite::init()` matches the request URI, so they work identically with pretty URLs on or off. The
entry id is read **only** from the route parameter (`Params::getParam('entry')`, cast to `int`) —
the form never posts an id field, so there is nothing for a visitor to override.

### Public form (`views/removal-form.php`)

Requires the requester's relation to the complex (`propietario`/`administrador`/`otro`), and
optionally collects a reply contact (≤190 chars) and a reason (≤1000 chars). Shows a Ley 25.326
privacy note, a CSRF token (`osc_csrf_token_form()`), and a hidden honeypot field (`website`,
CSS-offscreen, `autocomplete="off"`, `tabindex="-1"`).

The POST handler (`tourist_directory_init_custom_removal_post()`, wired to `init_custom`) runs
`osc_csrf_check()` first (exits on failure), then `tourist_directory_removal_decide()` (pure, unit
tested) in this exact precedence: honeypot filled → fake success, nothing written; validation error →
rejected with a form error; entry marker missing → generic "could not process" message; a blocking
request already exists → the same confirmation as a fresh success, no new row; per-IP (≥4 prior
requests/hour) or per-entry (≥2 prior requests/24h) throttle → the same generic "could not process"
message as a missing entry; entry already retired → insert only; otherwise → **insert the request row
first, then retire the entry** (never the reverse — if the insert fails, nothing changes and no
entry is retired unrecorded; if the retire fails after a successful insert, the request stays
`pending` and visible to the admin). No response ever reveals whether an id is a real entry, whether
it was already removed, or why it was rejected beyond "check the form" vs. "try again later".

IP is resolved via `tourist_directory_client_ip($_SERVER)` (never `osc_get_ip()`, which trusts
spoofable `Client-IP`/`X-Forwarded-For` headers) and stored only as
`tourist_directory_ip_hash($ip, $salt)` (`hash_hmac('sha256', ...)`) — the raw IP is never written to
the database.

### Admin screen (`admin/requests.php`, menu: "Tourist Directory Entries")

Lists every request (newest first): entry name, date, relation, status, reason, and the reply
contact — the reply contact is visible **only** on this screen, never publicly. Two actions, both
CSRF-protected and PRG (`renderplugin_controller`):

- **Mark processed** — `pending → processed`. Available only while `pending`.
- **Reactivate** — requires the confirm checkbox (`confirm=1`); rejects every currently-blocking
  (`pending`/`processed`) request for that item (not only the clicked row) to `rejected`, then
  reactivates the item. This is the only path, besides the CLI's `--allow-reactivate`, that can ever
  bring a removal-requested item back — and it is always an explicit, admin-initiated action, never
  automatic.

Retention housekeeping runs on every admin page load: `s_ip_hash` is nulled 30 days after
`dt_requested`; `s_reply_contact`/`s_reason` are blanked 180 days after `dt_processed`. The status row
itself is kept forever, so importer precedence never regresses.

### Importer precedence

`tourist_directory_plan()` checks `$flags['removal_seed_ids']` before any other state: a `candidato`
row whose seed id carries a blocking request (pending or processed) is always skipped
(`removal_requested`) — with or without a marker, retired or not, even with `--allow-reactivate` —
and reported in `removal_blocked`. `baja`/`anuncio_propio` rows are unaffected. The CLI re-checks
freshly, per row, immediately before each create/update/reactivate write (a race guard against a
request submitted between planning and applying), and `tourist_directory_enable()` excludes items
with a blocking request from its own non-retired auto-reactivation. See "Production import gate"
under "Importer CLI" above for the `--apply` channel-availability gate.
