# Directory Entries Specification

## Purpose

Defines factual-only "directory entries" — admin/importer-created Osclass items for commercial tourist complexes shown alongside owner listings — meeting the 8 approved conditions in `investigacion-complejos-turisticos.md`: no contact, no price, no copied content, visible label, removal path, no implied affiliation.

## Requirements

### Requirement: Directory Entry Public Rendering

The system MUST render a directory entry in its destination category, search results, and the home listing like an owner listing, with the label "Información pública, no gestionada por el complejo" (en_US fallback), a badge, an "Visitar sitio oficial" link to the official website, and a "Solicitar baja" removal link. It MUST NOT render a price for a directory entry.

#### Scenario: Entry appears in category, search, and home with required elements

- GIVEN an active directory entry
- WHEN a visitor browses its category, searches the site, or loads home over HTTPS
- THEN it appears with label, badge, "Visitar sitio oficial" link, "Solicitar baja" link, and no price

### Requirement: No Intermediated Contact UI

The system MUST NOT render a contact form, "send to a friend" form, or comment form/list on a directory entry's detail page.

#### Scenario: Contact chrome hidden on entry detail page

- GIVEN a directory entry's public detail page
- WHEN a visitor loads it over HTTPS
- THEN no contact form, send-to-friend form, or comment UI is rendered

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

### Requirement: Seed Importer Contract

The importer MUST read only public seed fields (`nombre`, `localidad`, `destino`, `tipo`, `web`), never `contacto`, `notas`, or state fields. It MUST import only rows with `estado_catalogo = candidato` and non-empty `web`. It MUST be idempotent by seed `id`: re-running creates no duplicates and updates changed public fields on an existing entry. It MUST NOT send email. For a row with `estado_catalogo` of `baja` or `anuncio_propio`, it MUST deactivate (not delete) the corresponding entry, reversibly.

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

### Requirement: Production Import Gate

The importer MUST refuse to run against a production target while the site's mail configuration or `osclass.contactEmail` is a placeholder value.

#### Scenario: Import blocked with placeholder contact email

- GIVEN `osclass.contactEmail` still holds its placeholder value
- WHEN an operator attempts a production import run
- THEN the importer refuses and imports nothing

#### Scenario: Import proceeds once mail is verified

- GIVEN `osclass.contactEmail` is set to a verified working address
- WHEN an operator runs the production import
- THEN the importer proceeds

### Requirement: Reversible Uninstall

Uninstalling or deactivating the `tourist-directory` plugin MUST deactivate every directory entry it manages and MUST NOT delete the underlying Osclass items or data.

#### Scenario: Uninstall deactivates without deleting

- GIVEN active directory entries exist
- WHEN an admin uninstalls or deactivates the `tourist-directory` plugin
- THEN every managed entry becomes deactivated and no underlying item row is deleted
