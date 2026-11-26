import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import { isPortalAudience } from '@/utils/portalAudience'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { PERMISSIONS } from '@/constants/permissionKeys'

// A signed-in entity follows the award from its path page inside the portal; the public intro is for visitors
// (03 §8.9, Q-197; AJ-04).
export function awardEntryGuard() {
  const auth = useAuthStore()
  const ownsAwardRequests =
    isPortalAudience(auth.roles) && usePermissions().can(PERMISSIONS.ASSESSMENT_ADVANCED_MANAGE)
  return ownsAwardRequests ? { name: ROUTE_NAMES.PATHS_AWARD, replace: true } : true
}
