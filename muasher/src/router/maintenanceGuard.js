import { useSiteStatusStore } from '@/stores/siteStatus'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { isPortalAudience } from '@/utils/portalAudience'

export function isPublicVisitorRoute(route) {
  return (
    route.meta.layout === 'public' && !route.meta.requiresAuth && !route.meta.allowDuringMaintenance
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
