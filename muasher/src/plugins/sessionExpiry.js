import { onSessionExpired } from '@/api/sessionEvents'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/composables/useToast'
import { i18n } from '@/i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'

export function installSessionExpiry(router) {
  return onSessionExpired(() => {
    const auth = useAuthStore()
    if (!auth.isAuthenticated) return
    auth.clearSession()
    toast.error(i18n.global.t('auth.session.expired'))
    const current = router.currentRoute.value
    if (current.meta.requiresAuth) {
      router.push({ name: ROUTE_NAMES.AUTH_LOGIN, query: { redirect: current.fullPath } })
    }
  })
}
