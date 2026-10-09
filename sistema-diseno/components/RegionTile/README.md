# RegionTile

The home page's main way in: one solid tile per region, listing its destinations as links.

- **Provide:** an `article.at-region-tile` with `style="background: var(--region-…)"`, an `h3` with the region name, a `ul` of destination links and a final "Ver toda la región" link. Wrap all six in `div.at-region-grid`.
- Region name in `display-lg`, expanded. Destination links in `on-ink`, underlined.
- Destination names are short ("Mar del Plata", not "Costa Atlántica – Mar del Plata").
- Order: Buenos Aires, Córdoba, Cuyo, Litoral, Norte, Patagonia.
- **Don't:** add icons, photos, counts that aren't real, or shadows. The tiles are the page's one bold element.
