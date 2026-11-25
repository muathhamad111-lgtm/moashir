import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { TABLE_COLUMNS } from '@/constants/tableColumns'
import { COLUMN_KINDS, columnKindOf } from '@/utils/tableColumns'
import { sameOrder } from '@/utils/reorder'

// Where a table's columns sit: the optional ones, and the locked ones before (the identifier) and
// after them (the actions). The locked data columns count toward the minimum; the actions do not.
function columnLayout(columns, offered) {
  const isOptional = (column) => offered.value.includes(column.key)
  const firstOptional = computed(() => columns.value.findIndex(isOptional))
  const lockedAt = (before) =>
    computed(() =>
      columns.value.filter(
        (column, index) =>
          !isOptional(column) &&
          (before ? index < firstOptional.value : index > firstOptional.value),
      ),
    )
  const leading = lockedAt(true)
  const trailing = lockedAt(false)

  return {
    leading,
    trailing,
    byKey: computed(() => new Map(columns.value.map((column) => [column.key, column]))),
    optionalKeys: computed(() => columns.value.filter(isOptional).map((column) => column.key)),
    lockedDataCount: computed(
      () =>
        [...leading.value, ...trailing.value].filter(
          (column) => columnKindOf(column, columns.value.indexOf(column)) !== COLUMN_KINDS.ACTIONS,
        ).length,
    ),
  }
}

// Columns this view of the table does not have (the advanced path's cycle and region) keep their
// place in the choice, so a change made here does not drop them elsewhere.
function withAbsent(keys, stored, byKey) {
  return [...keys, ...stored.filter((key) => !byKey.has(key) && !keys.includes(key))]
}

// A table's default columns in this view's column order (`defaults`), then those only other views
// of the table have (`tableDefaults`, the form the account compares a choice with).
function defaultsOf(tableKey, { offered, optionalKeys, byKey }) {
  const defaultKeys = computed(() => {
    const hidden = TABLE_COLUMNS[tableKey.value]?.hidden ?? []
    return offered.value.filter((key) => !hidden.includes(key))
  })
  const defaults = computed(() =>
    optionalKeys.value.filter((key) => defaultKeys.value.includes(key)),
  )
  const tableDefaults = computed(() => [
    ...defaults.value,
    ...defaultKeys.value.filter((key) => !byKey.value.has(key)),
  ])
  return { defaults, tableDefaults }
}

// The viewer's columns for one list table: which optional columns show, and in what order. Columns
// the table does not list as optional (the identifier before them, the actions after) always show.
// The account keeps the visible columns as an ordered list (the viewer's saved default); hidden ones
// follow in the table's order. «تطبيق» changes only this page's view, which lasts until the viewer leaves.
export function useTableColumnChoice(tableKey, columns) {
  const auth = useAuthStore()
  const store = useTableColumnsStore()
  const offered = computed(() => TABLE_COLUMNS[tableKey.value]?.columns ?? [])

  watch(
    () => auth.user?.id ?? null,
    (userId) => store.claim(userId),
    { immediate: true },
  )

  const { leading, trailing, byKey, optionalKeys, lockedDataCount } = columnLayout(columns, offered)

  const { defaults, tableDefaults } = defaultsOf(tableKey, { offered, optionalKeys, byKey })
  const present = (keys) => keys.filter((key) => byKey.value.has(key))
  const saved = computed(() => present(store.choices[tableKey.value] ?? tableDefaults.value))
  const viewKeys = ref(null)
  const visible = computed(() => (viewKeys.value ? present(viewKeys.value) : saved.value))
  const differsFromSaved = computed(() => !sameOrder(visible.value, saved.value))

  const shown = computed(() => [
    ...leading.value,
    ...visible.value.map((key) => byKey.value.get(key)),
    ...trailing.value,
  ])

  // Every optional column for the manager: the visible ones in the viewer's order, then the rest.
  const ordered = computed(() => [
    ...visible.value.map((key) => byKey.value.get(key)),
    ...optionalKeys.value
      .filter((key) => !visible.value.includes(key))
      .map((key) => byKey.value.get(key)),
  ])
  // This page only: the saved default stays as it is.
  function applyToView(keys) {
    viewKeys.value = [...keys]
  }

  // The account keeps the columns; the table's own defaults are kept as "no choice", so a later
  // change to them reaches this viewer.
  function saveAsDefault(keys) {
    const stored = store.choices[tableKey.value] ?? tableDefaults.value
    const choice = withAbsent(keys, stored, byKey.value)
    store.choose(tableKey.value, sameOrder(choice, tableDefaults.value) ? null : choice)
    viewKeys.value = null
  }

  return {
    shown,
    ordered,
    leading,
    trailing,
    visible,
    defaults,
    lockedDataCount,
    differsFromSaved,
    applyToView,
    saveAsDefault,
  }
}
