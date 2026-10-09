# Breadcrumb

Shows where a page sits in region → destination → accommodation.

- **Provide:** a `nav.at-breadcrumb` with `aria-label="Ubicación"` and an `ol`; separators are `li aria-hidden="true"` with "/"; the last item is plain text with `aria-current="page"`.
- On a region band add `at-breadcrumb-on-band`; on white add `at-breadcrumb-quiet`.
- Start at the region, not at the site name (the header already links home).
- Use short names: "Mina Clavero", not "Mina Clavero (Valle de Traslasierra)".
