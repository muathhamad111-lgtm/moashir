import { onIdentityRequired } from '@/api/sessionEvents'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'

export function installIdentityRequirement(router) {
  return onIdentityRequired(() => {
    const auth = useAuthStore()
    if (!auth.isAuthenticated) return
    auth.requireNationalId()
    const current = router.currentRoute.value
    if (current.name === ROUTE_NAMES.ACCOUNT_COMPLETE_IDENTITY) return
    router.push({
      name: ROUTE_NAMES.ACCOUNT_COMPLETE_IDENTITY,
      query: { redirect: current.fullPath },
    })
  })
}
