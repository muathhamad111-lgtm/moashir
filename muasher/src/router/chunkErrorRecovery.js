import { isChunkLoadError } from '@/utils/chunkLoadError'
import { createReloadGuard } from '@/utils/reloadGuard'
import { logger } from '@/utils/logger'
import { recoveryLocationFor } from './chunkRecoveryTarget'

export const PRELOAD_ERROR_EVENT = 'vite:preloadError'

function currentHref(location) {
  return `${location.pathname}${location.search}${location.hash}`
}

export function installChunkErrorRecovery(
  router,
  { browser = window, guard = createReloadGuard() } = {},
) {
  let navigating = false

  function claimReload(target, context) {
    const allowed = guard.claim(target)
    const outcome = allowed ? 'chunk.recovery.reload' : 'chunk.recovery.blocked'
    logger.breadcrumb('navigation', outcome, context)
    return allowed
  }

  function recoverNavigation(error, to) {
    navigating = false
    if (!to || !isChunkLoadError(error)) return
    const target = router.resolve(recoveryLocationFor(to)).href
    if (claimReload(target, { source: 'router', route: to.name })) browser.location.assign(target)
  }

  function recoverPreload(event) {
    if (navigating || !isChunkLoadError(event.payload)) return
    const target = currentHref(browser.location)
    if (claimReload(target, { source: 'preload' })) browser.location.reload()
  }

  router.beforeEach(() => {
    navigating = true
  })
  router.afterEach(() => {
    navigating = false
  })
  router.onError(recoverNavigation)
  browser.addEventListener(PRELOAD_ERROR_EVENT, recoverPreload)
}
