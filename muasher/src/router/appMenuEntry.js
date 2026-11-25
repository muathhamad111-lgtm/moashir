import { useAuthStore } from '@/stores/auth'
import { isPortalAudience } from '@/utils/portalAudience'
import { ROUTE_NAMES } from '@/constants/routeNames'

// The back office's «المزيد» (/menu). Entity-only accounts have their own «المزيد» (/more).
export function appMenuEntryGuard() {
  return isPortalAudience(useAuthStore().roles) ? { name: ROUTE_NAMES.PORTAL_MORE } : true
}
