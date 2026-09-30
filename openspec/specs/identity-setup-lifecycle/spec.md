# Identity Setup Lifecycle Specification

## Purpose

Defines the idempotent, reversible setup lifecycle for the `tourist-identity` plugin — apply, snapshot, restore, and deployed-copy re-sync — so the branding/catalog transformation is reproducible from the repository and safely undoable.

## Requirements

### Requirement: Idempotent Apply

The system MUST produce the same resulting state (preferences, category tree/flags/parent, currency rows, gettext map, logo) whether the setup or re-apply routine runs once or multiple times. Re-running MUST NOT create duplicate categories: the routine MUST consult the persisted created-id map (`tourist_identity.category_map`) before inserting a region or leaf row, and MUST skip any row already present in the map.
(Previously: re-running the setup routine MUST be a no-op relative to prior successful state, with no created-id tracking.)

#### Scenario: Re-apply changes nothing

- GIVEN the identity plugin has already been applied successfully
- WHEN the "Re-apply" action is triggered again
- THEN no preference, category, or currency row values differ from before the re-run
- AND the operation completes without error

#### Scenario: Re-apply creates no duplicate categories

- GIVEN the tree was already fully applied and its created-id map is populated
- WHEN "Re-apply" runs a second time
- THEN no new category rows are inserted for any region or leaf already in the map
- AND the total category row count is unchanged from before the re-run

### Requirement: Snapshot on Install

The system MUST capture prior values (core prefs, category `b_enabled`/parent state, currency flags/default, sigma prefs) into a dedicated `tourist_identity` preference section before applying any change. On a site where the plugin was already applied under the prior single-category model, the system MUST additionally capture a supplementary snapshot of category 47's pre-tree es_ES/en_US name and description before the first destination-tree apply, since the original install snapshot may lack them.
(Previously: only the original pre-existing values were snapshotted before the first change, with no supplementary capture for already-installed sites.)

#### Scenario: Snapshot precedes first change

- GIVEN the identity plugin is activated for the first time
- WHEN the install hook begins execution
- THEN a snapshot of pre-existing values is persisted under section `tourist_identity`
- AND the snapshot is written before any target value is modified

#### Scenario: Supplementary snapshot for already-installed sites

- GIVEN the plugin was already applied and category 47 currently holds "Alquiler Vacacional"
- WHEN the destination-tree apply routine runs for the first time on this site
- THEN category 47's pre-tree name and description are captured into a supplementary snapshot before it is renamed
- AND the original install snapshot is left unchanged

### Requirement: Restore on Uninstall

The system MUST restore all snapshotted values (prefs, category 47's flags/parent/name/description, currency flags/default, logo) when the plugin is uninstalled, and MUST leave the ARS currency row present but disabled. For every category row created by the tree apply routine, the system MUST delete rows with zero linked items and MUST disable (not delete) rows holding items, keeping their ids in the created-id map for future reuse. Every id the routine deletes MUST also be removed from `tourist_showcase.category_ids`, if the `tourist-showcase` plugin is active, so that showcase linkage never references a category that no longer exists.
(Previously: restore covered prefs, category 47 flags/parent, currency, and logo only, with no handling for plugin-created rows.)

#### Scenario: Uninstall reverts identity changes

- GIVEN the identity plugin was applied and a snapshot exists
- WHEN the plugin is uninstalled
- THEN `pageTitle`, `pageDesc`, category `b_enabled`/parent state, and currency default/flags return to their snapshotted values
- AND the ARS currency row remains in `oc_t_currency` with `b_enabled=0`

#### Scenario: Item deletion is not reverted

- GIVEN items 1 and 2 were deleted during setup
- WHEN the plugin is uninstalled
- THEN items 1 and 2 are not recreated
- AND the uninstall completes successfully despite this irreversible step

#### Scenario: Category 47 is restored to its original identity

- GIVEN category 47 was repurposed as "Buenos Aires" during tree apply
- WHEN the plugin is uninstalled
- THEN category 47's name, description, `b_enabled`, and parent return to the values captured in its snapshot

#### Scenario: Created rows are deleted if empty, disabled if in use

- GIVEN the tree apply routine created region and leaf categories, some holding items and some empty
- WHEN the plugin is uninstalled
- THEN every plugin-created row with zero linked items is deleted
- AND every plugin-created row holding items is disabled, with its id kept in the created-id map

#### Scenario: Showcase linkage is pruned of deleted categories

- GIVEN the `tourist-showcase` plugin is active and its `category_ids` preference lists leaf ids the tree apply routine created
- WHEN the plugin is uninstalled and some of those leaf ids are deleted for holding zero items
- THEN the deleted ids no longer appear in `tourist_showcase.category_ids`
- AND any surviving (disabled, not deleted) id remains in `tourist_showcase.category_ids`

### Requirement: HTTPS-Verifiable Success

The system MUST expose its applied state so that success is verifiable entirely through public HTTPS responses, without database access, including the full 6-region/51-leaf destination tree.
(Previously: HTTPS-verifiable state covered title, H1, placeholder, logo, disclaimer, the single category, ARS-only currency, and items 1/2 returning 404/410.)

#### Scenario: End-to-end public verification

- GIVEN identity setup has completed
- WHEN an operator checks the public HTTPS site
- THEN title, H1, placeholder, logo, disclaimer, the 6 regions with their 51 leaves, ARS-only currency, and items 1/2 returning 404 or 410 (Osclass returns 410 Gone for deleted items) are all observable from HTTP responses alone
- AND "Powered by Osclass" does not appear anywhere on the page

### Requirement: Created-Category Tracking

The system MUST persist a created-id map (`tourist_identity.category_map`) recording, for every region and leaf the tree apply routine creates, a stable key and the resulting category id. The routine MUST consult this map before every insert to decide whether a row already exists.

#### Scenario: New category id is recorded on first creation

- GIVEN a leaf destination has never been created
- WHEN the tree apply routine inserts its category row
- THEN the resulting category id is written into `tourist_identity.category_map` under that leaf's stable key

#### Scenario: Map entry is reused on subsequent runs

- GIVEN a leaf's key is already present in `tourist_identity.category_map`
- WHEN the tree apply routine runs again
- THEN the routine uses the mapped id instead of inserting a new row

### Requirement: Visible Re-Apply Result

The system MUST render the outcome of the last "Re-apply" run inline on the plugin's configure screen, MUST NOT rely solely on the transient flash message, and MUST report the exact count of changes made (or "0 change(s)" when none occurred).

#### Scenario: Configure screen shows change count after re-apply

- GIVEN an operator triggers "Re-apply" from the configure screen
- WHEN the action completes
- THEN the configure screen renders the number of changes made inline on the page
- AND this result is visible without depending on the flash message

#### Scenario: Second re-apply shows zero changes

- GIVEN the destination tree and all identity settings are already fully applied
- WHEN an operator triggers "Re-apply" a second time
- THEN the configure screen inline result reads "0 change(s)"

### Requirement: Deployed Copy Re-Sync

The system MUST require the deployed plugin copy under `app/osclass/oc-content/plugins/` to be re-synced from source before activation or update, and the deployed copy MUST NOT be edited directly.

#### Scenario: Stale deployed copy is refreshed before activation

- GIVEN plugin source has changed under `plugins/tourist-identity/`
- WHEN the plugin is (re)activated or updated in admin
- THEN the deployed copy matches the source copy byte-for-byte
- AND no edits exist in the deployed path that are absent from source
