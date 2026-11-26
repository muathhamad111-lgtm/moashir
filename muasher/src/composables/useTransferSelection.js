import { computed, ref, unref, watch } from 'vue'
import { BULK_DECISION_MAX } from '@/constants/bankTransfer'

export function useTransferSelection(items, { isSelectable = () => true } = {}) {
  const selectedIds = ref([])

  const selectable = computed(() => (unref(items) ?? []).filter(isSelectable))
  const selectableIds = computed(() => selectable.value.map((item) => item.id))
  const selected = computed(() =>
    selectable.value.filter((item) => selectedIds.value.includes(item.id)),
  )
  const count = computed(() => selected.value.length)
  const allSelected = computed(
    () => selectableIds.value.length > 0 && count.value === selectableIds.value.length,
  )
  const someSelected = computed(() => count.value > 0 && !allSelected.value)
  const overLimit = computed(() => count.value > BULK_DECISION_MAX)

  function isSelected(id) {
    return selectedIds.value.includes(id)
  }

  function toggle(id, checked = !isSelected(id)) {
    if (!selectableIds.value.includes(id)) return
    selectedIds.value = checked
      ? [...new Set([...selectedIds.value, id])]
      : selectedIds.value.filter((value) => value !== id)
  }

  function toggleAll(checked = !allSelected.value) {
    selectedIds.value = checked ? [...selectableIds.value] : []
  }

  function clear() {
    selectedIds.value = []
  }

  watch(selectableIds, (ids) => {
    selectedIds.value = selectedIds.value.filter((id) => ids.includes(id))
  })

  return {
    selectedIds,
    selected,
    count,
    allSelected,
    someSelected,
    overLimit,
    isSelected,
    toggle,
    toggleAll,
    clear,
  }
}
