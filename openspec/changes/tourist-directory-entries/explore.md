# Exploration: tourist-directory-entries

> Mirror of Engram `sdd/tourist-directory-entries/explore`. Orchestrator verified: `pre_item_contact_post` runs before `ItemActions::contact()` (`controller/item.php:628-631`), `pre_item_send_friend_post` (`:541`) and `pre_item_add_comment_post` (`:650`) exist, and `osc_redirect_to()` ends with `exit` (`utils.php:2641`).

## Goal

Admin-created "directory entries" (factual-only Osclass items: name, destination category, accommodation type, link to the complex's official website) that meet the 8 approved conditions in `investigacion-complejos-turisticos.md`: no contact form, no price, no copied content, visible label "Información pública, no gestionada por el complejo", removal path, no implied relationship; shown in the same categories/search/home as owner listings; retired when the complex publishes its own listing; imported from the seed (public fields only).

## Vendor facts

| Topic | Fact | Source |
| --- | --- | --- |
| Programmatic creation | Vendor seeds demo items with `Params::setParam` + `new ItemActions(true)` + `prepareData(true)` + `add()` | `install-functions.php:614-625` |
| No emails on admin create | `sendEmails()` only when `!is_admin` | `ItemActions.php:311-313` |
| Guest item | No matching user → `fk_i_user_id` null | `ItemActions.php:1785` |
| Price | Empty price → `i_price` NULL, currency null; `osc_format_price` applies `item_price_null` filter | `ItemActions.php:202-204`, `hItems.php:1486-1498` |
| Contact send guard | `pre_item_contact_post` / `pre_item_send_friend_post` / `pre_item_add_comment_post` fire before sending; a redirect exits | `controller/item.php:541,628,650`, `utils.php:2641` |
| Comments | Email the item contact and admin | `emails.php:1412-1413` |
| Global toggles only | Contact/send-friend disabled prefs are site-wide | `hPreference.php:192-204` |
| Contact field filters | `item_contact_name/email/phone/other` | `hItems.php:431-463` |
| Show email/phone | `b_show_email` / `b_show_phone` default false | `ItemActions.php:1865-1866` |
| Theme hooks | item: `item_title`, `item_description_after`, `item_detail`; sidebar: `item_sidebar_top`, `item_contact_form`; cards: `item_loop_description`, `item_loop_bottom` | `sigma/item.php`, `item-sidebar.php`, `loop-single.php` |
| Contact box chrome | Not wrapped by a hook; needs CSS keyed off a body class injected on `init_item` | `item-sidebar.php:76-82`, `sigma/functions.php:141-177` |
| Location | City/region/country free text are optional; no location tree needed | `hValidate.php:34-41`, `ItemActions.php:156-161,259-279` |
| Retire | `ItemActions::delete` (hard, 410) or `deactivate` (soft) | `ItemActions.php:1174` |

## Approaches

1. Marker in a **dedicated plugin table** `t_directory_entry(fk_i_item_id, s_seed_id UNIQUE, s_official_website, s_source_url, dt_imported, dt_retired)` — no public-form leakage, idempotency key, clean uninstall; new pattern here. **Recommended.**
2. Marker as a **Field/meta** — existing convention, but category-linked fields render on the public post form (visitor could set it). Rejected.
3. Import via **CLI script** — keeps `data/prospeccion/` off any web path, pure row mapping testable; new bootstrap shape. **Recommended first.**
4. Import via **Configure-screen button** — familiar UX; HTTP timeouts for large batches.

## Recommendation

New plugin (e.g. `plugins/tourist-directory/`): table marker; `i_price = NULL` + `item_price_null` filter; `b_show_email/phone = 0`; fail-closed guards on the three `pre_item_*_post` hooks; label, official link and removal link via item/card hooks; small CSS for the unhooked contact box; CLI importer reading only public seed fields, idempotent by seed id.

## Risks

- Any guard gap (or failing open on lookup error) would route an enquiry to the item's contact email — guards must fail closed.
- Seed `tipo` values differ from the showcase dropdown (`Apartamento|Casa|Cabaña|Habitación privada|Hostería|Otro`).
- Directory entries have no guests/bedrooms values, so those filters exclude them.
- CLI bootstrap is unprecedented in this repo.
- Hard delete on retire is irreversible.

## Open decisions

1. Marker (table recommended). 2. Import tool (CLI recommended). 3. `tipo` mapping. 4. Guests/bedrooms filter asymmetry. 5. Retire: delete vs deactivate. 6. Removal-request channel.

## Spec impact

New capability `directory-entries`; existing specs unchanged unless the filter asymmetry requires a note.
