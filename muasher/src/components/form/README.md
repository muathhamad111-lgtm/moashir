# components/form

The unified field system: BaseInput, BaseTextarea, BaseDatePicker, BaseCheckbox, BaseRadio,
BaseFileUpload, FormField — see `references/06-architecture.md`. Validation is vee-validate + yup
(one schema per form). Styling only via `src/styles/components/_inputs.scss`.

`BaseDatePicker` (and `FieldDate`) is three Western-digit boxes (day / month / year, in the reading
direction) plus a calendar button. Arabic digits are converted while typing, and a whole date typed
or pasted into one box fills all three; the value is `YYYY-MM-DD` once the date is complete, `''`
otherwise. The field `name` sits on the day box. The button opens `DateCalendarPopover`: the in-app
Gregorian calendar (`DateCalendar`, WAI-ARIA APG date picker dialog: a labelled dialog with a
`role="grid"` month, the week starting on Sunday, `min`/`max` days disabled, arrows / PageUp /
PageDown / Home / End / Enter, Esc and focus return). It opens next to the field in the browser top
layer and as a bottom sheet below the `md` breakpoint, like the dropdown family. Month math lives in
`utils/calendarMonth.js`, names come from `Intl` in the active locale. Styling: `_datepicker.scss`,
`_date-calendar.scss`.

Dropdown family (Task-56, WAI-ARIA APG patterns, one popup surface for every kind):

- `AppSelect` is the select-only combobox. `variant="field"` sits in `FormField` (floating label,
  hint, error); `variant="filter"` is the toolbar filter with a visible label prefix. `FieldSelect`
  (vee-validate) and `committee/RankSelect` wrap it.
- `AppCombobox` is the editable combobox: local filter, `remote` search (debounced `search` event)
  and `multiple` with removable tags. `FieldIconSelect` wraps it with translated icon names.
  With `multiple`, `select-all` adds «تحديد الكل / إلغاء الكل» and a count (`count-label` overrides
  the generic «N من M»). A disabled option that is selected stays as a locked tag; its `hint` is the
  tag tooltip and «إلغاء الكل» keeps it.
- Both open in the browser top layer next to their field (`useAnchoredPopup`), so dialogs and
  table scroll containers never clip them, and as a bottom sheet below the `md` breakpoint.
- Styling: `_dropdown-field.scss`, `_dropdown-option.scss`, `_dropdown-surface.scss`,
  `_icon-field.scss`.
