import { toValue } from 'vue'
import { useRouter } from 'vue-router'

/**
 * The phone top bar's back button. It steps back only when the router recorded an in-app previous
 * entry (`history.state.back`, set by vue-router for navigations inside the SPA), so it never
 * leaves the platform; otherwise (a deep link, a new tab) it opens the owning tab's screen.
 */
export function useAppBackTarget(fallbackRoute) {
  const router = useRouter()

  function hasInAppHistory() {
    return typeof window.history.state?.back === 'string'
  }

  function goBack() {
    if (hasInAppHistory()) return router.back()
    return router.push(toValue(fallbackRoute))
  }

  return { goBack }
}
