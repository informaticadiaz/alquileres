# Field

A labelled text input or select, used in the publish form, contact and sign-up.

- **Provide:** a `div.at-field` holding a `<label for>` and an `input.at-input` or `select.at-select` with the matching `id`. Optional `p.at-help` with `aria-describedby`.
- The label is always visible and associated: never use the placeholder as the label.
- Label in `label` style, sentence case; helper text in `caption`.
- Border `border-control`; height 48px; radius `radius-md`.
- Privacy defaults are off: a checkbox that publishes a phone or email starts unchecked.
- **Don't:** ask for fields that don't apply to accommodation (price per unit, neighbourhood as free text) or leave a field without a label.
