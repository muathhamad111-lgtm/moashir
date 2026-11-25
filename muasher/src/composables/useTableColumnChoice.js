import { computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { TABLE_COLUMNS } from '@/constants/tableColumns'

// The viewer's column choice for one list table: which columns show, and what the «الأعمدة» menu
// offers. Columns the table does not list as optional (its first column, the actions) always show.
export function useTableColumnChoice(tableKey, columns) {
  const auth = useAuthStore()
  const store = useTableColumnsStore()
  const offered = computed(() => TABLE_COLUMNS[tableKey.value]?.columns ?? [])

  watch(
    () => auth.user?.id ?? null,
    (userId) => store.claim(userId),
    { immediate: true },
  )

  const defaults = computed(() => {
    const hidden = TABLE_COLUMNS[tableKey.value]?.hidden ?? []
    return offered.value.filter((key) => !hidden.includes(key))
  })
  const visible = computed(() => store.choices[tableKey.value] ?? defaults.value)
  const isOptional = (column) => offered.value.includes(column.key)

  const shown = computed(() =>
    columns.value.filter((column) => !isOptional(column) || visible.value.includes(column.key)),
  )
  const optional = computed(() => columns.value.filter(isOptional))
  const locked = computed(() => columns.value.filter((column) => !isOptional(column)))

  function toggle(key, show) {
    const next = new Set(visible.value)
    if (show) next.add(key)
    else next.delete(key)
    store.choose(
      tableKey.value,
      offered.value.filter((column) => next.has(column)),
    )
  }

  function reset() {
    store.choose(tableKey.value, null)
  }

  return { shown, optional, locked, visible, toggle, reset }
}
