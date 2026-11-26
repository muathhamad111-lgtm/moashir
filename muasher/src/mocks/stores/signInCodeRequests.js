import { CODE_REQUEST_LIMITS } from '../fixtures/auth'

const MS_PER_SECOND = 1000
const byIdentifier = new Map()
let byAddress = []

export function resetSignInCodeRequests() {
  byIdentifier.clear()
  byAddress = []
}

function keyOf(identifier) {
  return `${identifier.type}:${identifier.value.toLowerCase()}`
}

function recentOf(timestamps) {
  const windowStart = Date.now() - CODE_REQUEST_LIMITS.windowSeconds * MS_PER_SECOND
  return timestamps.filter((at) => at > windowStart)
}

function waitSecondsOf(timestamps, limit) {
  if (timestamps.length < limit) return 0
  const freedAt = timestamps[0] + CODE_REQUEST_LIMITS.windowSeconds * MS_PER_SECOND
  return Math.max(1, Math.ceil((freedAt - Date.now()) / MS_PER_SECOND))
}

export function codeRequestWaitSeconds(identifier) {
  byAddress = recentOf(byAddress)
  const sameIdentifier = recentOf(byIdentifier.get(keyOf(identifier)) ?? [])
  byIdentifier.set(keyOf(identifier), sameIdentifier)
  return Math.max(
    waitSecondsOf(sameIdentifier, CODE_REQUEST_LIMITS.perIdentifier),
    waitSecondsOf(byAddress, CODE_REQUEST_LIMITS.perAddress),
  )
}

export function recordCodeRequest(identifier) {
  const now = Date.now()
  byAddress.push(now)
  byIdentifier.set(keyOf(identifier), [...(byIdentifier.get(keyOf(identifier)) ?? []), now])
}
