# components/form

The unified field system: BaseInput, BaseTextarea, BaseSelect, BaseDatePicker, BaseCheckbox,
BaseRadio, BaseFileUpload, FormField, SearchSelect (Select2 replacement) — see
`references/06-architecture.md`. Validation is vee-validate + yup (one schema per form).
Styling only via `src/styles/components/_inputs.scss` / `_select2-search.scss`.
`FieldIconSelect` is the icon picker (preview + SearchSelect listing TTS icon names with their glyph,
optional "no icon" choice), styled by `_icon-field.scss`.
