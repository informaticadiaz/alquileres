# Portal Catalog Baseline Specification

## Purpose

Defines the minimal public catalog baseline — a single top-level rental category, ARS as the sole enabled currency, and removal of seeded test listings — so the portal shows one coherent taxonomy and currency instead of the generic 95-category, multi-currency Osclass default.

## Requirements

### Requirement: Single Top-Level Category

The system MUST disable (`b_enabled=0`) every category except category 47, and MUST re-parent category 47 to root (top-level).

#### Scenario: Only one category is publicly listed

- GIVEN the identity plugin has been applied
- WHEN a visitor loads the public categories list over HTTPS
- THEN only "Alquiler Vacacional" (category 47) appears
- AND no other category or its URL returns a public listing

#### Scenario: Former parent category no longer serves listings

- GIVEN category 47 was previously a child of category 4
- WHEN a visitor requests the old child-category URL for 47 after apply
- THEN the category resolves at its new top-level path
- AND browsing the former parent (category 4) shows no linked listings

### Requirement: Category Linkage Preserved

The system MUST keep `tourist_showcase.category_ids` set to `"47"` after identity setup, including after a showcase plugin reinstall.

#### Scenario: Showcase fields remain linked to 47

- GIVEN both tourist-showcase and tourist-identity are active
- WHEN the identity setup (or re-apply) completes
- THEN `tourist_showcase.category_ids` equals `"47"`
- AND the showcase's five meta fields remain attached to category 47

#### Scenario: Linkage restored after showcase reinstall

- GIVEN tourist-showcase is reinstalled, resetting `category_ids` to `""`
- WHEN the identity plugin's re-apply action runs
- THEN `tourist_showcase.category_ids` is restored to `"47"`

### Requirement: ARS-Only Currency

The system MUST insert an ARS row into `oc_t_currency`, set the core preference `currency` to `ARS`, and disable (`b_enabled=0`) USD, EUR, and GBP.

#### Scenario: Publish form offers only ARS

- GIVEN the identity plugin has been applied
- WHEN a visitor opens the public "publish ad" form over HTTPS
- THEN the currency selector offers only ARS
- AND USD, EUR, and GBP are not selectable

### Requirement: Test Item Removal

The system MUST delete items 1 and 2 through the native Item manager during setup.

#### Scenario: Seeded test listings are gone

- GIVEN identity setup has completed
- WHEN a visitor requests the public detail page for item 1 or item 2 over HTTPS
- THEN the server returns HTTP 404 or 410 (Osclass returns 410 Gone for deleted items)
- AND neither item appears in public search results
