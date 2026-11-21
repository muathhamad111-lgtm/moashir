import { computed, shallowRef } from 'vue'

// The route a navigation is heading to while its guards run and its page chunk loads.
// Query-only changes (filters, tabs) keep the page and never count as a page load.
const pendingRoute = shallowRef(null)

export function installNavigationState(router) {
  router.beforeEach((to, from) => {
    pendingRoute.value = to.path === from.path ? null : to
  })
  router.afterEach((to) => {
    if (pendingRoute.value === to) pendingRoute.value = null
  })
  router.onError(() => {
    pendingRoute.value = null
  })
}

export function useNavigationState() {
  return { isLoadingPage: computed(() => pendingRoute.value !== null) }
}
