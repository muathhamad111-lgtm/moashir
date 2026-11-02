import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { KNOWN_PERMISSIONS } from '@/constants/permissionKeys'

export function usePermissions() {
  const auth = useAuthStore()
  const granted = computed(() => new Set(auth.permissions))
  const hasCommitteeAppointment = computed(() => auth.committeeAppointments.length > 0)
  const hasEntityScope = computed(() => Boolean(auth.user?.entityId))

  function can(key) {
    return KNOWN_PERMISSIONS.has(key) && granted.value.has(key)
  }

  function canAny(keys = []) {
    return keys.some((key) => can(key))
  }

  function canAll(keys = []) {
    return keys.length > 0 && keys.every((key) => can(key))
  }

  function canAccess(access) {
    if (!access) return true
    if (access.committeeAppointment && !hasCommitteeAppointment.value) return false
    if (access.entityScope && !hasEntityScope.value) return false
    if (access.allOf && !canAll(access.allOf)) return false
    return canAny(access.anyOf ?? [])
  }

  return { can, canAny, canAll, canAccess, hasCommitteeAppointment, hasEntityScope }
}
