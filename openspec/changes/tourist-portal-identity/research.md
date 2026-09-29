# Research: tourist-portal-identity

Status: done. Collected 2026-09-29 by `sdd-research` (web only); orchestrator resolved local gaps.

## Claims

| ID | Claim | Confidence | Sources |
| --- | --- | --- | --- |
| C1 | Argentine portals use an imperative H1 plus short owner CTA: "Publicá tu alojamiento en AlquilerArgentina.com" / "Publicar Alojamiento"; "Publicá tu propiedad en Argenprop" / "Cargar una propiedad". | High | [1], [2] |
| C2 | No fetched competitor shows a public "no bookings/payments" disclaimer; ArgenProp uses "informative only, non-contractual" listing notes (Ley 24.240 style). The disclaimer must be authored. | Medium | [2] |
| C3 | CCyC art. 1199: furnished lodging rented for tourism/rest is a temporary rental; above three months it is presumed non-touristic. | High | [3] |
| C4 | Ley 27.551 was repealed by DNU 70/2023; the art. 1199 carve-out persists in the Code. | Medium (secondary source; verify against primary text) | [4], [6] |
| C5 | CABA Ley 6255 places RAT registration duties on owners/lessors, not explicitly on listing platforms. CABA-only; other provinces not researched. | Medium | [5] |
| C6 | `__()` applies `osc_apply_filter('gettext', ...)`, enabling plugin-level string overrides. | **High — verified locally** in `app/osclass/oc-includes/osclass/helpers/hTranslations.php:35-39`. | [7] + local |
| C7 | Currencies are managed natively: `oc-admin/currencies.php` (Settings > Currencies) and model `oc-includes/osclass/model/Currency.php`; table `oc_t_currency(pk_c_code, s_name, s_description, b_enabled)` currently holds EUR, GBP, USD only. Default currency is core preference `currency` (currently `USD`). | **High — verified locally** | [8] + local |

## Copy candidates

| Field | es_ES | en_US |
| --- | --- | --- |
| Site title | Alquileres Temporarios — Casas, Cabañas y Departamentos en Argentina | Temporary Rentals — Houses, Cabins & Apartments in Argentina |
| Meta description | Encontrá alojamientos temporarios en toda Argentina: casas, cabañas, departamentos y hosterías. Contactá directamente al propietario. | Find short-term rentals across Argentina: houses, cabins, apartments and guesthouses. Contact owners directly. |
| Hero H1 | Publicá o encontrá tu alojamiento temporario en Argentina | Publish or find your short-term rental in Argentina |
| Search placeholder | Buscá por ciudad, provincia o tipo de alojamiento | Search by city, province or property type |
| Owner CTA | Publicar alojamiento | Publish your listing |
| Footer disclaimer | Alquileres Temporarios es una vitrina de anuncios: conectamos propietarios y huéspedes, pero no procesamos reservas ni pagos ni intervenimos como intermediarios en la contratación. Todo acuerdo se realiza directamente entre las partes. | Alquileres Temporarios is a listings showcase: we connect owners and guests, but we do not process bookings or payments and do not act as an intermediary in the agreement. All arrangements are made directly between the parties. |

## Gaps and risks

- Disclaimer copy is originated, not sourced; it needs a lightweight legal sanity check before real traffic (CCyC art. 1199, Ley 24.240 arts. 7-8).
- Provincial rules outside CABA not researched.
- C4 relies on a secondary source.

## Sources

1. https://www.alquilerargentina.com/publicar.html (accessed 2026-09-29)
2. https://www.argenprop.com/publicar (accessed 2026-09-29)
3. https://leyfacil.com.ar/codigo-civil-y-comercial/articulo-1199/ (accessed 2026-09-29)
4. https://roomix.ai/blog/alquiler-temporario-regulacion-argentina (accessed 2026-09-29)
5. https://abogados.com.ar/nueva-regulacion-de-los-alquileres-temporarios-con-fines-turisticos-en-la-caba/24958 (accessed 2026-09-29)
6. https://www.argentina.gob.ar/normativa/nacional/ley-27551-339378/texto (listed, not deep-fetched)
7. https://github.com/osclass/Osclass/blob/master/oc-includes/osclass/helpers/hTranslations.php
8. https://docs.osclass-classifieds.com/
