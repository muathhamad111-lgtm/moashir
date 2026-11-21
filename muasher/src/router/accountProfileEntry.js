import { useAuthStore } from '@/stores/auth'
import { isPortalAudience } from '@/utils/portalAudience'
import { ROUTE_NAMES } from '@/constants/routeNames'

export function accountProfileEntryGuard() {
  return isPortalAudience(useAuthStore().roles) ? { name: ROUTE_NAMES.ENTITY_PROFILE } : true
}
