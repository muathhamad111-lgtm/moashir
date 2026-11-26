import { useSiteStatusStore } from '@/stores/siteStatus'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { isPortalAudience } from '@/utils/portalAudience'

const VISITOR_LAYOUTS = Object.freeze(['public', 'landing'])

export function isPublicVisitorRoute(route) {
  return (
    VISITOR_LAYOUTS.includes(route.meta.layout) &&
    !route.meta.requiresAuth &&
    !route.meta.allowDuringMaintenance
  )
}

export function opensForSignedInPortalUser(route) {
  if (!route.meta.openForPortalUsers) return false
  const auth = useAuthStore()
  return auth.isAuthenticated && isPortalAudience(auth.roles)
}

export async function maintenanceRedirect(to) {
  if (!isPublicVisitorRoute(to) || opensForSignedInPortalUser(to)) return null
  const siteStatus = useSiteStatusStore()
  const maintenance = await siteStatus.ensureChecked()
  return maintenance ? { name: ROUTE_NAMES.PUBLIC_MAINTENANCE } : null
}
