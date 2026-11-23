# components/data-list

StatsCards, ListToolbar, DataTable, Pagination — the list-page pattern from
`references/06-architecture.md`, powered by useTable/useFilters/usePagination composables.
Row actions use `components/ui/AppMenu.vue` (menu button, top layer, bottom sheet on phones).
Styling via `_data-table.scss`, `_stats-card.scss`, `_toolbar.scss`, `_pagination.scss`.

**Stacked rows (`<DataTable stacked>`).** When the list's own width is narrow (under 40rem: a phone, or a
column beside a side panel), each row reads as one block: the first column as its title, the other cells as
label/value lines (the label comes from `column.label`), and the actions last at full width. A column is an
actions column when its key is `actions` or it sets `action: true`. Sortable headings stay as a row of chips.
The award staff tables use it (AJ-05, AJ-22); other tables are unchanged until they opt in.
