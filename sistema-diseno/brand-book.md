A public showcase of short-term rentals in Argentina, organized like a road guide: by region, then destination, then accommodation. It connects travelers with each place; it does not take bookings or payments. Design for travelers first and for a 390px phone first.

## Content fundamentals

- **Tone: a reliable guide.** Clear, ordered and neutral. Inform, never sell: no urgency, no superlatives, no invented figures, ratings or reviews.
- **Voice: Rioplatense voseo everywhere.** "Elegí un destino", "Consultá directo", "Publicá tu alojamiento". Never mix with tú ("Elige", "Publica").
- **Vocabulary.** Say *alojamiento* and *ficha*, never *anuncio*, *listado* or *oferta*. Say *sitio oficial* for the property's own website.
- **Two kinds of listing, always labelled.** *Ficha informativa*: built from public data, not managed by the property. *Publicado por el alojamiento*: managed by its owner. Use the `Badge` component for both.
- **Sentence case** for every heading, button and label. No all-caps labels, no eyebrows above headings, no emoji.
- **Actions say what happens.** "Ir al sitio oficial", "Solicitar baja", "Publicar alojamiento", never "Enviar" or "Click acá".
- **No placeholder copy.** If a fact is missing, leave the field out rather than writing generic text such as "es un Cabaña en …".

## Visual foundations

- **Color.** Pages sit on `surface` with `ink` text. `surface-alt` sets off bands and notice panels. Each region owns one color (`region-buenos-aires`, `region-cordoba`, `region-cuyo`, `region-litoral`, `region-norte`, `region-patagonia`) used only for that region: tile fills, the destination header band and region tags. Text on a region or `ink` fill is `on-ink`. Always pair a region color with the region's name; never rely on color alone.
- **The one bold move** is the region board and the expanded display type. Keep everything else quiet: no gradients, no shadows, no decorative borders.
- **Type.** One family, Archivo (Google Fonts, variable width and weight). `display-xl` and `display-lg` are set expanded (`font-stretch: 125%`), like road signage; everything else at normal width. Use `heading` for h2, `title` for accommodation names and h3, `body` for running text within `measure`, `body-sm` for metadata, `label` for field labels and tags, `caption` for helper text.
- **No photos required.** Today no listing has photos. Components never reserve space for an image that may not exist; a listing reads complete from its text.
- **Spacing.** Lay out with flex or grid and `gap`. Side gutter `space-4` on phones, `space-5` from tablet up. Sections are separated by `space-7` on phones and `space-8` above. Content never exceeds `container-max`.
- **Radii.** `radius-md` for controls, `radius-lg` for tiles, lists and panels, `radius-sm` for badges, `radius-pill` only for filter chips.
- **Borders and states.** `border` is a hairline between rows only. Controls use `border-control`. Selected filter chips fill with `ink`. Hover thickens a link's underline; nothing else moves.
- **Focus.** Every interactive element shows a 3px solid `focus` outline with a 2px offset.
- **Touch.** Anything tappable is at least `target-min` tall.
- **Motion.** None by default. Respect `prefers-reduced-motion` if any is added.

## Layout

- **Header:** only the site name (`SiteHeader`), linking home.
- **Navigation lives in the footer** (`SiteFooter`): Destinos, Cómo funciona, Ingresar, Publicar alojamiento, next to the showcase notice.
- **No search box on the home page.** Travelers explore through the region board (`RegionTile`).
- **Destination page:** a header band in the region color with `Breadcrumb` and `display-xl`, then `FilterChip` filters (one row, never a panel that hides results on a phone), then `ListingRow` items.
- **Listing page:** title, facts, and an action `Panel` that leads to the official site above the fold on a phone.

## Iconography

No icon set yet. Use inline stroke SVG at 20px, 1.75px stroke, colored with `currentColor`, only where a word alone is ambiguous. Never emoji.

## Logo

There is no logo. Set the site name in `title` weight 800, expanded, as `SiteHeader` does.

## Implementation notes

Components are plain HTML with the classes in `components/bundle.css` (prefix `at-`); there is no JavaScript bundle. Load Archivo from Google Fonts with the `wdth` axis: `family=Archivo:wdth,wght@62..125,100..900`.
