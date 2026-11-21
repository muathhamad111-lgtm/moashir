import { logger } from '@/utils/logger'

export const RELOAD_GUARD_KEY = 'moashir.chunkRecovery'
export const RELOAD_GUARD_WINDOW_MS = 30_000

function sessionStore() {
  return window.sessionStorage
}

function parseRecord(raw) {
  try {
    return JSON.parse(raw ?? 'null')
  } catch {
    return null
  }
}

export function createReloadGuard({
  storage = sessionStore,
  now = Date.now,
  windowMs = RELOAD_GUARD_WINDOW_MS,
} = {}) {
  let claimed = false

  function claimInStorage(target) {
    const store = storage()
    const last = parseRecord(store.getItem(RELOAD_GUARD_KEY))
    const at = now()
    if (last?.target === target && at - last.at < windowMs) return false
    store.setItem(RELOAD_GUARD_KEY, JSON.stringify({ target, at }))
    return true
  }

  function storageClaim(target) {
    try {
      return claimInStorage(target)
    } catch {
      logger.breadcrumb('navigation', 'chunk.recovery.storage_unavailable')
      return true
    }
  }

  function claim(target) {
    if (claimed || !storageClaim(target)) return false
    claimed = true
    return true
  }

  return { claim }
}
