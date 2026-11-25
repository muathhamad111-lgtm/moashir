import { computed, ref } from 'vue'
import { MIN_VISIBLE_COLUMNS } from '@/constants/tableColumns'

// What the dialog reads off the working rows: the minimum, the visible count, the name search.
function summarize(rows, query, choice) {
  const minimum = computed(() =>
    Math.min(rows.value.length, Math.max(0, MIN_VISIBLE_COLUMNS - choice.lockedDataCount.value)),
  )
  const shownKeys = computed(() => rows.value.filter((row) => row.shown).map((row) => row.key))
  const needle = computed(() => query.value.trim().toLocaleLowerCase())

  return {
    minimum,
    shownKeys,
    shownCount: computed(() => shownKeys.value.length + choice.lockedDataCount.value),
    atMinimum: computed(() => shownKeys.value.length <= minimum.value),
    allShown: computed(() => rows.value.every((row) => row.shown)),
    filtered: computed(() =>
      needle.value
        ? rows.value.filter((row) => row.label.toLocaleLowerCase().includes(needle.value))
        : rows.value,
    ),
  }
}

// The «إدارة الأعمدة» dialog's working copy: the optional columns in order, each shown or hidden,
// a name search, and the minimum of visible columns. Nothing is saved until the viewer applies it.
export function useColumnDraft(choice) {
  const rows = ref([])
  const query = ref('')
  const summary = summarize(rows, query, choice)

  function fill(keysShown) {
    const columns = choice.ordered.value
    const labels = new Map(columns.map((column) => [column.key, column.label]))
    const order = [
      ...keysShown,
      ...columns.map((column) => column.key).filter((key) => !keysShown.includes(key)),
    ]
    rows.value = order.map((key) => ({
      key,
      label: labels.get(key),
      shown: keysShown.includes(key),
    }))
    query.value = ''
  }

  const canHide = (row) => !row.shown || !summary.atMinimum.value

  function setShown(key, shown) {
    const row = rows.value.find((candidate) => candidate.key === key)
    if (row && (shown || canHide(row))) row.shown = shown
  }

  // Select all shows every column; clearing it keeps the first ones up to the minimum.
  function setAll(shown) {
    rows.value.forEach((row, index) => {
      row.shown = shown || index < summary.minimum.value
    })
  }

  function reorder(keys) {
    const byKey = new Map(rows.value.map((row) => [row.key, row]))
    rows.value = keys.map((key) => byKey.get(key))
  }

  return {
    ...summary,
    query,
    load: () => fill(choice.visible.value),
    resetToDefaults: () => fill(choice.defaults.value),
    canHide,
    setShown,
    setAll,
    reorder,
    result: () => summary.shownKeys.value,
  }
}
