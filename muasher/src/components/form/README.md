# components/form

The unified field system: BaseInput, BaseTextarea, BaseDatePicker, BaseCheckbox, BaseRadio,
BaseFileUpload, FormField — see `references/06-architecture.md`. Validation is vee-validate + yup
(one schema per form). Styling only via `src/styles/components/_inputs.scss`.

`BaseDatePicker` (and `FieldDate`) is three Western-digit boxes (day / month / year, in the reading
direction) plus a calendar button that opens the browser picker of a hidden native date input. The
native field is not shown because Chrome in Arabic renders its placeholder reversed. Arabic digits
are converted while typing; the value is `YYYY-MM-DD` once the date is complete, `''` otherwise.
Styling: `_datepicker.scss`.

Dropdown family (Task-56, WAI-ARIA APG patterns, one popup surface for every kind):

- `AppSelect` is the select-only combobox. `variant="field"` sits in `FormField` (floating label,
  hint, error); `variant="filter"` is the toolbar filter with a visible label prefix. `FieldSelect`
  (vee-validate) and `committee/RankSelect` wrap it.
- `AppCombobox` is the editable combobox: local filter, `remote` search (debounced `search` event)
  and `multiple` with removable tags. `FieldIconSelect` wraps it with translated icon names.
- Both open in the browser top layer next to their field (`useAnchoredPopup`), so dialogs and
  table scroll containers never clip them, and as a bottom sheet below the `md` breakpoint.
- Styling: `_dropdown-field.scss`, `_dropdown-option.scss`, `_dropdown-surface.scss`,
  `_icon-field.scss`.
