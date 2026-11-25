import { computed, reactive } from 'vue'
import { PROFILE_CHANGE_COUPLED, PROFILE_CHANGE_NEEDS } from '@/constants/entityEnums'

function partnersOf(item) {
  return PROFILE_CHANGE_COUPLED.find((group) => group.includes(item)) ?? [item]
}

/**
 * Which items of a change request the reviewer approves. Stale items, and a new authorized person sent without an
 * authorization letter, can only be rejected; coupled items always share one decision.
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

  function lacksPartner(item) {
    const partner = PROFILE_CHANGE_NEEDS[item]
    return Boolean(partner) && !rowsRef.value.some((row) => row.item === partner)
  }

  function isLocked(item) {
    return isStale(item) || lacksPartner(item)
  }

  function reset() {
    Object.keys(approved).forEach((key) => delete approved[key])
    rowsRef.value.forEach((row) => {
      approved[row.item] = !isLocked(row.item)
    })
  }

  function setApproved(item, value) {
    const present = partnersOf(item).filter((partner) => partner in approved)
    if (value && present.some(isLocked)) return
    present.forEach((partner) => {
      approved[partner] = value
    })
  }

  function setAll(value) {
    rowsRef.value.forEach((row) => setApproved(row.item, value && !isLocked(row.item)))
  }

  return { approved, approvedItems, rejectedCount, isLocked, reset, setApproved, setAll }
}
