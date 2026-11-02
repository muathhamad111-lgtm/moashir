import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROLES } from '@/constants/roles'

export function useCycleAuthority() {
  const auth = useAuthStore()
  const { can } = usePermissions()
  const isSystemAdministrator = computed(() => auth.isSuperAdmin || (auth.roles.includes(ROLES.SYSTEM_ADMIN) && can(PERMISSIONS.AWARD_CYCLES_MANAGE)))
  const canCreate = computed(() => auth.isSuperAdmin || can(PERMISSIONS.AWARD_CYCLES_MANAGE))
  const canReopen = computed(() => auth.isSuperAdmin)
  return { isSystemAdministrator, canCreate, canReopen }
}
