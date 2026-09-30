# Proposal: Tourist Directory Entries

## Intent

Publish factual-only "directory entries" for commercial complexes (approved 2026-09-30, `investigacion-complejos-turisticos.md`) alongside owner listings, meeting all 8 binding conditions: no contact, price, copied content or implied affiliation; visible label; removal path.

## Scope

### In Scope
- New plugin `plugins/tourist-directory/` (pure lib + glue, strict TDD) with table `t_directory_entry` (item id, seed id UNIQUE, official website, source url, imported/retired timestamps).
- Entry rendering: label "Información pública, no gestionada por el complejo" (en_US fallback), "Visitar sitio oficial", "Solicitar baja" (`page=contact`), card badge, `init_item` body class + CSS hiding contact box chrome.
- Fail-closed guards on `pre_item_contact_post`, `pre_item_send_friend_post`, `pre_item_add_comment_post` (lookup error blocks).
- No price (`i_price` NULL + `item_price_null`), `b_show_email/phone = 0`, no photos, no copied description.
- Operator CLI importer: public seed fields only; `candidato` + `web` rows; idempotent upsert by seed id; `baja`/`anuncio_propio` → deactivate.
- Extend showcase dropdown with "Apart hotel", "Complejo de departamentos"; mapping `departamentos_con_servicios` → "Apartamento", `cabanas` → "Cabaña".

### Out of Scope
- Deleting items; bulk admin UI; mail configuration; guests/bedrooms values for entries (excluded by those filters); legal review itself.

## Capabilities

### New Capabilities
- `directory-entries`: marker, public rendering, fail-closed contact guards, importer contract, retirement, production-import gate, accommodation-type vocabulary (incl. the two new dropdown values).

### Modified Capabilities
- None. No existing spec covers the showcase dropdown; its extension is specified in `directory-entries`.

## Approach

Explore approaches 1 + 3: plugin table marker (no public-form leakage) and CLI importer creating items via `ItemActions(true)` as admin (no emails, guest owner). Design decides: neutral generated description, placeholder-`contactEmail` refusal in the importer, CLI bootstrap, and how existing field `s_options` get updated (showcase install only inserts missing fields).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `plugins/tourist-directory/` | New | Plugin, lib, CLI importer, CSS |
| `plugins/tourist-showcase/tourist-showcase-lib.php` | Modified | Dropdown options |
| `tests/test_tourist_showcase.php` | Modified | Pure-contract tests |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Guard gap routes enquiry to complex | Med | Fail closed on all three hooks; HTTPS POST checks |
| Perceived affiliation / trademark | Med | Label, no contact, lawyer review before volume |
| Removal requests lost (mail unset) | High | Production-import gate |
| Existing dropdown options not updated | Med | Explicit options update in design |

## Rollback Plan

Deactivate/uninstall `tourist-directory` (entries deactivated, table kept or dropped per design); revert dropdown options; restore pre-deploy DB backup if needed.

## Dependencies

- Working site mail and real `osclass.contactEmail` (gate for production import).
- User-performed deploy: DB backup, file sync, admin install, CLI import.

## Success Criteria

- [ ] `php tests/test_tourist_showcase.php` passes.
- [ ] Over HTTPS, an entry shows label, official link, removal link, badge; no price, contact form, send-to-friend or comments.
- [ ] Direct POSTs to contact/send-friend/comment for an entry are rejected.
- [ ] Re-running the importer creates no duplicates; `baja` rows deactivate.
- [ ] Dropdown offers the two new values; existing values intact.
- [ ] No production import until mail is verified working.
