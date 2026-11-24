import { computed, ref, shallowRef, toValue } from 'vue'
import { committeeService } from '@/services/committeeService'
import { usePermissions } from '@/composables/usePermissions'
import { SEAT_STATUSES } from '@/constants/committee'
import { PERMISSIONS } from '@/constants/permissionKeys'

export function useCycleCommittee(cycle) {
  const { canAny } = usePermissions()
  const seats = shallowRef([])
  const canManage = ref(false)
  const loaded = ref(false)

  const readable = computed(
    () =>
      Boolean(toValue(cycle)?.revealsStaff) &&
      canAny([PERMISSIONS.AWARD_COMMITTEE_MANAGE, PERMISSIONS.AWARD_SECRETARY_REVIEW]),
  )
  const activeSeats = computed(() =>
    seats.value.filter((seat) => seat.status === SEAT_STATUSES.ACTIVE),
  )
  const names = computed(() =>
    loaded.value ? activeSeats.value.map((seat) => seat.user?.name).filter(Boolean) : null,
  )

  async function load() {
    if (!readable.value) return
    try {
      const envelope = await committeeService.listMembers(toValue(cycle).id)
      seats.value = envelope.data
      canManage.value = envelope.meta?.canManage === true
      loaded.value = true
    } catch {
      loaded.value = false
      canManage.value = false
    }
  }

  return { activeSeats, names, canManage, loaded, load }
}
