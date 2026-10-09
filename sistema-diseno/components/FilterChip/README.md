# FilterChip

A toggle that filters the listings of a destination or region by type.

- **Provide:** a `div.at-chips` with `role="group"` and an `aria-label` ("Filtrar por tipo"), holding `button.at-chip` elements with `aria-pressed`. Each label names the option and its count: "Cabaña (5)".
- "Todos" comes first and is pressed by default.
- Only offer options that have results; never a chip with a count of 0.
- Chips sit in one wrapping row above the results. On a phone they never push the first result below the fold.
- **Don't:** reintroduce the side filter panel, or filter by price, photos or bathrooms while listings carry no such data.
