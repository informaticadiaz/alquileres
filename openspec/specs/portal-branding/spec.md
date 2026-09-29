# Portal Branding Specification

## Purpose

Defines the public-facing identity of the site — title, meta description, logo, hero/search copy, and footer disclaimer — so visitors immediately see an Argentine short-term-rental portal instead of a generic Osclass install, with zero leakage into admin pages.

## Requirements

### Requirement: Site Title and Meta Description

The system MUST set the core preference `pageTitle` to "Alquileres Temporarios" and `pageDesc` to the es_ES meta description defined by the identity plugin during setup.

#### Scenario: Home page reflects identity title

- GIVEN the identity plugin has been applied
- WHEN a visitor loads the public home page over HTTPS
- THEN the page `<title>` contains "Alquileres Temporarios"
- AND the meta description tag contains the configured es_ES description

### Requirement: Public-Only Gettext Overrides

The system MUST register `gettext` filter overrides for the hero H1, owner CTA, and other listed public strings, and MUST NOT apply these overrides when rendering `OC_ADMIN` pages.

#### Scenario: Public hero string is overridden

- GIVEN the identity plugin is active
- WHEN a visitor loads the public home page over HTTPS
- THEN the hero H1 reads "Encontrá tu alojamiento temporario en Argentina"
- AND the owner CTA reads "Publicar alojamiento"

#### Scenario: Admin strings remain untouched

- GIVEN the identity plugin is active
- WHEN an administrator loads any `oc-admin` page
- THEN none of the overridden public strings replace core/admin strings
- AND original Osclass admin wording is displayed unchanged

### Requirement: Search Placeholder and Footer Credit

The system MUST set the sigma preference `keyword_placeholder` to "Buscá por ciudad, provincia o tipo de alojamiento" and MUST set `footer_link` to `0`.

#### Scenario: Search box shows portal placeholder

- GIVEN the identity plugin has been applied
- WHEN a visitor views the public search box over HTTPS
- THEN the placeholder text reads "Buscá por ciudad, provincia o tipo de alojamiento"
- AND the footer does not display "Powered by Osclass"

### Requirement: Footer Disclaimer

The system MUST render the researched es_ES/en_US disclaimer text in the public footer via the `footer` hook, without appearing on admin pages.

#### Scenario: Disclaimer is visible on public pages

- GIVEN the identity plugin is active
- WHEN a visitor views any public page footer over HTTPS
- THEN the disclaimer text is present
- AND the disclaimer does not appear on `oc-admin` pages

### Requirement: Logo Installation

The system MUST install the plugin's SVG/PNG logo asset as the sigma theme `logo` preference during setup, without requiring a manual admin upload.

#### Scenario: Logo replaces default sigma logo

- GIVEN the identity plugin has been applied
- WHEN a visitor loads the public home page over HTTPS
- THEN the header logo image resolves to the plugin's installed asset
- AND the default `sigma_logo.png` is no longer referenced
