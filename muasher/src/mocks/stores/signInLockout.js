import { CODE_LIMITS, LOCKOUT_POLICY } from '../fixtures/auth'

const MS_PER_SECOND = 1000
const failures = new Map()
const issuedCodes = new Map()

function keyOf(account) {
  return `${account.kind}:${account.record.id}`
}

function secondsUntil(timestamp) {
  return Math.max(0, Math.ceil((timestamp - Date.now()) / MS_PER_SECOND))
}

export function resetSignInLockout() {
  failures.clear()
  issuedCodes.clear()
}

export function lockedSecondsOf(account) {
  const entry = failures.get(keyOf(account))
  return entry?.lockedUntil ? secondsUntil(entry.lockedUntil) : 0
}

export function recordFailure(account) {
  const entry = failures.get(keyOf(account)) ?? { count: 0, lockedUntil: null }
  entry.count += 1
  if (entry.count >= LOCKOUT_POLICY.maxAttempts) {
    entry.count = 0
    entry.lockedUntil = Date.now() + LOCKOUT_POLICY.lockSeconds * MS_PER_SECOND
  }
  failures.set(keyOf(account), entry)
  return lockedSecondsOf(account)
}

export function clearFailures(account) {
  failures.delete(keyOf(account))
}

export function codeCooldownSecondsOf(account) {
  const windowStart = Date.now() - CODE_LIMITS.windowSeconds * MS_PER_SECOND
  const recent = (issuedCodes.get(keyOf(account)) ?? []).filter((at) => at > windowStart)
  issuedCodes.set(keyOf(account), recent)
  if (recent.length < CODE_LIMITS.perTenMinutes) return 0
  return secondsUntil(recent[0] + CODE_LIMITS.windowSeconds * MS_PER_SECOND)
}

export function recordIssuedCode(account) {
  issuedCodes.set(keyOf(account), [...(issuedCodes.get(keyOf(account)) ?? []), Date.now()])
}
