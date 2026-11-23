import { computed, reactive } from 'vue'
import { PROFILE_CHANGE_COUPLED } from '@/constants/entityEnums'

function partnersOf(item) {
  return PROFILE_CHANGE_COUPLED.find((group) => group.includes(item)) ?? [item]
}

/**
 * Which items of a change request the reviewer approves. Stale items can only be rejected, and coupled
 * items (region and city, VAT registration and number) always share one decision.
 */
export function useProfileChangeSelection(rowsRef) {
  const approved = reactive({})

  const approvedItems = computed(() =>
    rowsRef.value.filter((row) => approved[row.item]).map((row) => row.item),
  )
  const rejectedCount = computed(() => rowsRef.value.length - approvedItems.value.length)

  function isStale(item) {
    return rowsRef.value.some((row) => row.item === item && row.isStale)
  }

  function reset() {
    Object.keys(approved).forEach((key) => delete approved[key])
    rowsRef.value.forEach((row) => {
      approved[row.item] = !row.isStale
    })
  }

  function setApproved(item, value) {
    const present = partnersOf(item).filter((partner) => partner in approved)
    if (value && present.some(isStale)) return
    present.forEach((partner) => {
      approved[partner] = value
    })
  }

  function setAll(value) {
    rowsRef.value.forEach((row) => setApproved(row.item, value && !row.isStale))
  }

  return { approved, approvedItems, rejectedCount, reset, setApproved, setAll }
}
