# Delta for Portal Catalog Baseline

## RENAMED Requirements

### Requirement: Single Top-Level Category → Destination Tree Categories

(Reason: replaces the single enabled category model with a region→destination tree)
(Migration: None — new behavior is captured in the MODIFIED block below)

## MODIFIED Requirements

### Requirement: Destination Tree Categories

The system MUST enable exactly 6 region categories at root, in confirmed order Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia, and MUST enable the 51 confirmed leaf destinations as children of their assigned region, in `research.md` relevance order with each region's catch-all leaf last. The system MUST repurpose category 47 in place as the "Buenos Aires" region (same id, root position, `b_enabled=1`). The system MUST keep the 94 native default categories disabled (`b_enabled=0`), never deleted. Posting MUST offer only the 51 leaves as selectable options; regions MUST render as non-selectable optgroups.
(Previously: the system disabled every category except 47 and re-parented 47 to root as the sole top-level category.)

#### Scenario: Regions appear at root in confirmed order

- GIVEN the identity plugin has applied the destination tree
- WHEN a visitor loads the public categories list over HTTPS
- THEN exactly 6 root categories appear, in order: Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia
- AND no other root category is publicly listed

#### Scenario: Leaves nest under their region in relevance order

- GIVEN the tree has been applied
- WHEN a visitor browses a region's category page over HTTPS
- THEN its leaf destinations appear in `research.md` order
- AND the region's "Otros destinos de <region>" catch-all is the last leaf listed

#### Scenario: Category 47 becomes the Buenos Aires region

- GIVEN category 47 was previously named "Alquiler Vacacional"
- WHEN the tree apply routine runs
- THEN category 47 keeps its id and stays enabled at root
- AND its es_ES/en_US name and description become "Buenos Aires"

#### Scenario: Native defaults stay disabled, not deleted

- GIVEN the 94 native default categories exist in `oc_t_category`
- WHEN the tree apply routine runs
- THEN every default category row still exists with `b_enabled=0`
- AND none of the 94 rows is deleted

#### Scenario: Publish form only allows leaf selection

- GIVEN the tree has been applied
- WHEN a visitor opens the public "publish ad" form over HTTPS
- THEN each region renders as a non-selectable optgroup label
- AND only the 51 leaf destinations are selectable options

### Requirement: Category Linkage Preserved

The system MUST keep `tourist_showcase.category_ids` set to the full comma-separated list of the 51 leaf destination ids after identity setup, including after a showcase plugin reinstall, and MUST attach the showcase's five meta fields to every one of those 51 leaf ids.
(Previously: `tourist_showcase.category_ids` was fixed to `"47"`, linking the five meta fields to category 47 only.)

#### Scenario: Showcase fields linked to every leaf

- GIVEN both tourist-showcase and tourist-identity are active after tree apply
- WHEN the identity setup (or re-apply) completes
- THEN `tourist_showcase.category_ids` contains exactly the 51 leaf destination ids
- AND the five meta fields are attached to each of those 51 ids

#### Scenario: Linkage restored after showcase reinstall

- GIVEN tourist-showcase is reinstalled, resetting `category_ids` to `""`
- WHEN the identity plugin's re-apply action runs
- THEN `tourist_showcase.category_ids` is restored to the full 51-leaf id list
