# Directory Entries Specification

## Purpose

Defines factual-only "directory entries" — admin/importer-created Osclass items for commercial tourist complexes shown alongside owner listings — meeting the 8 approved conditions in `investigacion-complejos-turisticos.md`: no contact, no price, no copied content, visible label, removal path, no implied affiliation. Site mail configuration is postponed; removal runs through a plugin-owned, mail-independent request channel.

## Requirements

### Requirement: Directory Entry Public Rendering

The system MUST render a directory entry in its destination category, search results, and the home listing like an owner listing, with the label "Información pública, no gestionada por el complejo" (en_US fallback), a badge, a "Visitar sitio oficial" link to the official website, and a "Solicitar baja" link to that entry's public removal request form. It MUST NOT render a price for a directory entry.

#### Scenario: Entry appears in category, search, and home with required elements

- GIVEN an active directory entry
- WHEN a visitor browses its category, searches the site, or loads home over HTTPS
- THEN it appears with label, badge, "Visitar sitio oficial" link, "Solicitar baja" link to the removal request form, and no price

### Requirement: No Intermediated Contact UI

The system MUST NOT make a contact form, "send to a friend" form, or comment form/list visible or usable on a directory entry's detail page. Theme markup that the plugin cannot omit without editing vendor files MAY remain in the HTML only if it is hidden from display and assistive technology (`display: none`) and every submission it could produce is blocked server-side by the Fail-Closed Contact Guards.

#### Scenario: Contact chrome not visible on entry detail page

- GIVEN a directory entry's public detail page
- WHEN a visitor loads it over HTTPS with the site styles applied
- THEN no contact form, send-to-friend form, or comment UI is visible
- AND any such markup still present in the HTML is hidden with `display: none`
- AND submitting it is blocked by the Fail-Closed Contact Guards

### Requirement: Fail-Closed Contact Guards

The system MUST block `pre_item_contact_post`, `pre_item_send_friend_post`, and `pre_item_add_comment_post` for a directory entry, sending no email or comment, including for a forged direct POST that bypasses the UI. It MUST also block when the marker lookup errors (fail closed), and MUST NOT block these actions for a non-entry (owner) item.

#### Scenario: Forged direct POSTs to contact, send-friend, and comment are blocked

- GIVEN a directory entry item id
- WHEN a client sends a direct POST to its contact, send-friend, or add-comment action, bypassing the UI
- THEN each request is rejected before any email or comment is created

#### Scenario: Marker lookup failure fails closed

- GIVEN the marker lookup for an item errors
- WHEN any of the three POST actions is attempted for that item
- THEN the request is rejected, same as for a confirmed entry

#### Scenario: Owner listing contact is unaffected

- GIVEN an item with no directory-entry marker
- WHEN a visitor submits its contact form over HTTPS
- THEN the contact email is sent as before

### Requirement: Filter Scope

Guests and bedrooms filters MUST exclude directory entries (they carry no such values). The accommodation-type filter MUST include directory entries.

#### Scenario: Guests/bedrooms exclude entries, type filter includes them

- GIVEN a directory entry typed "Cabaña" with no guests/bedrooms values
- WHEN a visitor filters by guests or bedrooms, then by type "Cabaña", over HTTPS
- THEN the entry is absent from the guests/bedrooms results and present in the type results

### Requirement: Accommodation Type Vocabulary

The showcase accommodation-type dropdown MUST offer "Apart hotel" and "Complejo de departamentos" in addition to its previous values (Apartamento, Casa, Cabaña, Habitación privada, Hostería, Otro). An already-installed site MUST have its stored options updated to include the two new values, not only a fresh install.

#### Scenario: New values available on fresh and existing sites

- GIVEN the showcase plugin, freshly installed or previously installed
- WHEN an admin or visitor opens the accommodation-type field over HTTPS
- THEN "Apart hotel" and "Complejo de departamentos" are selectable alongside all previous values

### Requirement: Removal Request Form

The system MUST render a public, plugin-owned removal request form for a directory entry, reachable from the "Solicitar baja" link, that works without any site mail configuration. The form MUST be scoped to exactly one entry, with the entry id fixed and not editable by the visitor. It MUST require the requester's relation to the complex (e.g., propietario, administrador, otro), and MAY collect an optional reply contact and an optional reason. It MUST display a visible privacy note stating the data is used only to process the removal request, per Ley 25.326. It MUST be CSRF-protected and apply anti-abuse controls: a honeypot field and a per-IP/per-entry submission throttle. It MUST validate and length-limit all input and escape all output. On a valid submission the system MUST record the request as pending review, store no email send, deactivate the entry immediately and reversibly, and show the visitor a confirmation. A duplicate valid submission for an already-removed entry MUST be accepted idempotently, without an error that leaks entry state.

#### Scenario: Valid submission deactivates the entry and records the request

- GIVEN an active directory entry's removal request form
- WHEN a visitor submits it over HTTPS with a valid relation value
- THEN the entry is deactivated immediately, the request is recorded as pending review with no email sent, and the visitor sees a confirmation

#### Scenario: Missing required relation is rejected

- GIVEN the removal request form
- WHEN a visitor submits it without selecting a relation to the complex
- THEN the submission is rejected with a validation error and no request is recorded

#### Scenario: CSRF failure, honeypot trip, or throttle rejects the submission

- GIVEN the removal request form for an entry
- WHEN a submission fails CSRF validation, trips the honeypot, or exceeds the per-IP/entry throttle
- THEN the submission is rejected and neither a request record nor an entry deactivation occurs

#### Scenario: Duplicate request on an already-removed entry is idempotent

- GIVEN a directory entry already deactivated by a prior removal request
- WHEN a visitor submits another valid removal request for the same entry
- THEN the request is accepted with the visitor shown a confirmation, and no error reveals the entry's prior removal state

### Requirement: Removal Request Admin

The system MUST provide a plugin admin screen listing removal requests with entry name, request date, relation, and status. The optional reply contact MUST be visible only on this admin screen. An admin MUST be able to mark a request processed. An admin MUST be able to reactivate the associated entry only as an explicit action (e.g., for an abusive request); reactivation MUST NOT happen automatically.

#### Scenario: Admin views removal requests with reply contact restricted

- GIVEN one or more recorded removal requests
- WHEN an admin opens the removal-requests admin screen
- THEN entry name, date, relation, and status are listed, and the optional reply contact is visible only there

#### Scenario: Admin marks a request processed

- GIVEN a pending removal request
- WHEN an admin marks it processed
- THEN its status updates to processed and the associated entry stays deactivated

#### Scenario: Admin explicitly reactivates an entry for an abusive request

- GIVEN a removal request an admin judges abusive
- WHEN the admin explicitly reactivates the associated entry
- THEN the entry becomes active again and the reactivation action is recorded

### Requirement: Seed Importer Contract

The importer MUST read only public seed fields (`nombre`, `localidad`, `destino`, `tipo`, `web`), never `contacto`, `notas`, or state fields. It MUST import only rows with `estado_catalogo = candidato` and non-empty `web`. It MUST be idempotent by seed `id`: re-running creates no duplicates and updates changed public fields on an existing entry. It MUST NOT send email. For a row with `estado_catalogo` of `baja` or `anuncio_propio`, it MUST deactivate (not delete) the corresponding entry, reversibly. It MUST NOT recreate or reactivate an entry that has a recorded removal request (pending or processed), even when reactivation is otherwise requested, and MUST report such seed ids so the operator can set the seed row to `baja`/`no_contactar`.

#### Scenario: Initial import creates entries without email

- GIVEN a seed with `candidato` rows carrying `web`
- WHEN the importer runs
- THEN one entry is created per row from public fields only, and no email is sent

#### Scenario: Re-run is idempotent and updates changed fields

- GIVEN entries already imported
- WHEN the importer runs again with one row's `localidad` changed
- THEN no duplicate is created and the existing entry's `localidad` is updated

#### Scenario: Baja and anuncio_propio rows deactivate entries reversibly

- GIVEN an imported entry whose row changes to `estado_catalogo = baja` or `anuncio_propio`
- WHEN the importer runs
- THEN the entry is deactivated, not deleted, and reversing the row can reactivate it

#### Scenario: Importer skips and reports an entry with a removal request

- GIVEN an entry with a recorded removal request, pending or processed
- WHEN the importer runs, including with a reactivation flag set
- THEN the entry is neither recreated nor reactivated, and its seed id is reported for the operator to set to `baja`/`no_contactar`

### Requirement: Production Import Gate

The importer MUST refuse to run with `--apply` against a production target unless the removal channel is available — the removal-request storage owned by the installed plugin is present. It MUST NOT hard-gate on `osclass.contactEmail` being a placeholder value; a placeholder MAY produce a warning only, and MUST NOT block the run.

#### Scenario: Import blocked without an available removal channel

- GIVEN the plugin's removal-request storage is not present (plugin not installed or outdated)
- WHEN an operator attempts `--apply` against production
- THEN the importer refuses and imports nothing

#### Scenario: Import proceeds once the removal channel is available

- GIVEN the removal-request storage is present in the installed plugin
- WHEN an operator runs `--apply` against production, even while `osclass.contactEmail` is still a placeholder
- THEN the importer proceeds, optionally emitting a placeholder-contact warning

### Requirement: Reversible Uninstall

Uninstalling or deactivating the `tourist-directory` plugin MUST deactivate every directory entry it manages and MUST NOT delete the underlying Osclass items or data.

#### Scenario: Uninstall deactivates without deleting

- GIVEN active directory entries exist
- WHEN an admin uninstalls or deactivates the `tourist-directory` plugin
- THEN every managed entry becomes deactivated and no underlying item row is deleted
