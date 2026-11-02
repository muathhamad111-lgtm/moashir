import { useSiteStatusStore } from '@/stores/siteStatus'
import { ROUTE_NAMES } from '@/constants/routeNames'

export function isPublicVisitorRoute(route) {
  return route.meta.layout === 'public' && !route.meta.requiresAuth && !route.meta.allowDuringMaintenance
}

export async function maintenanceRedirect(to) {
  if (!isPublicVisitorRoute(to)) return null
  const siteStatus = useSiteStatusStore()
  const maintenance = await siteStatus.ensureChecked()
  return maintenance ? { name: ROUTE_NAMES.PUBLIC_MAINTENANCE } : null
}
