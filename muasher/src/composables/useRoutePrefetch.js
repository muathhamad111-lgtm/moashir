import { onBeforeUnmount, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { warmRouteChunks } from '@/router/routeChunks'
import { prefetchRegistrationReads } from '@/composables/registration/registrationReads'

const ROUTE_DATA_PREFETCH = Object.freeze({
  [ROUTE_NAMES.AUTH_REGISTER]: prefetchRegistrationReads,
})
const INTENT_EVENTS = Object.freeze(['pointerover', 'focusin', 'touchstart'])
const IDLE_TIMEOUT_MS = 2000

function whenIdle(callback) {
  if (typeof window.requestIdleCallback === 'function') {
    const id = window.requestIdleCallback(callback, { timeout: IDLE_TIMEOUT_MS })
    return () => window.cancelIdleCallback(id)
  }
  const id = window.setTimeout(callback, IDLE_TIMEOUT_MS)
  return () => window.clearTimeout(id)
}

function sameOriginHref(event) {
  const link = event.target?.closest?.('a[href]')
  if (!link || link.origin !== window.location.origin) return null
  return `${link.pathname}${link.search}`
}

export function useIdleRouteWarmup(routeNames) {
  const router = useRouter()
  let cancel = null

  onMounted(() => {
    cancel = whenIdle(() => routeNames.forEach((name) => warmRouteChunks(router, { name })))
  })

  onBeforeUnmount(() => cancel?.())
}

export function useRouteIntentPrefetch(rootRef) {
  const router = useRouter()

  function onIntent(event) {
    const href = sameOriginHref(event)
    if (!href) return
    const route = router.resolve(href)
    if (!route.matched.length) return
    warmRouteChunks(router, route)
    ROUTE_DATA_PREFETCH[route.name]?.()
  }

  onMounted(() => {
    for (const type of INTENT_EVENTS)
      rootRef.value?.addEventListener(type, onIntent, { passive: true })
  })

  onBeforeUnmount(() => {
    for (const type of INTENT_EVENTS) rootRef.value?.removeEventListener(type, onIntent)
  })
}
