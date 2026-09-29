# Identity Setup Lifecycle Specification

## Purpose

Defines the idempotent, reversible setup lifecycle for the `tourist-identity` plugin — apply, snapshot, restore, and deployed-copy re-sync — so the branding/catalog transformation is reproducible from the repository and safely undoable.

## Requirements

### Requirement: Idempotent Apply

The system MUST produce the same resulting state (preferences, category flags/parent, currency rows, gettext map, logo) whether the setup routine runs once or multiple times; re-running it MUST be a no-op relative to prior successful state.

#### Scenario: Re-apply changes nothing

- GIVEN the identity plugin has already been applied successfully
- WHEN the "Re-apply" action is triggered again
- THEN no preference, category, or currency row values differ from before the re-run
- AND the operation completes without error

### Requirement: Snapshot on Install

The system MUST capture prior values (core prefs, category `b_enabled`/parent state, currency flags/default, sigma prefs) into a dedicated `tourist_identity` preference section before applying any change.

#### Scenario: Snapshot precedes first change

- GIVEN the identity plugin is activated for the first time
- WHEN the install hook begins execution
- THEN a snapshot of pre-existing values is persisted under section `tourist_identity`
- AND the snapshot is written before any target value is modified

### Requirement: Restore on Uninstall

The system MUST restore all snapshotted values (prefs, category flags/parent of 47, currency flags/default, logo) when the plugin is uninstalled, and MUST leave the ARS currency row present but disabled.

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

### Requirement: Deployed Copy Re-Sync

The system MUST require the deployed plugin copy under `app/osclass/oc-content/plugins/` to be re-synced from source before activation or update, and the deployed copy MUST NOT be edited directly.

#### Scenario: Stale deployed copy is refreshed before activation

- GIVEN plugin source has changed under `plugins/tourist-identity/`
- WHEN the plugin is (re)activated or updated in admin
- THEN the deployed copy matches the source copy byte-for-byte
- AND no edits exist in the deployed path that are absent from source

### Requirement: HTTPS-Verifiable Success

The system MUST expose its applied state so that success is verifiable entirely through public HTTPS responses, without database access.

#### Scenario: End-to-end public verification

- GIVEN identity setup has completed
- WHEN an operator checks the public HTTPS site
- THEN title, H1, placeholder, logo, disclaimer, single category, ARS-only currency, and items 1/2 returning 404 are all observable from HTTP responses alone
- AND "Powered by Osclass" does not appear anywhere on the page
