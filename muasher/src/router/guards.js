import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { accessFor } from '@/constants/routeAccess'
import { maintenanceRedirect } from './maintenanceGuard'

export function setupGuards(router) {
  router.beforeEach(async (to) => {
    const auth = useAuthStore()
    await auth.ensureSession()
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { name: ROUTE_NAMES.AUTH_LOGIN, query: { redirect: to.fullPath } }
    }
    if (to.meta.guestOnly && auth.isAuthenticated) {
      return { name: ROUTE_NAMES.APP_DASHBOARD }
    }
    if (to.meta.requiresAuth && !usePermissions().canAccess(accessFor(to.name))) {
      return { name: ROUTE_NAMES.ERRORS_FORBIDDEN }
    }
    return (await maintenanceRedirect(to)) ?? true
  })
}
