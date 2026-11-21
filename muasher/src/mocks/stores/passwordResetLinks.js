import { RECOVERY_TOKENS } from '../fixtures/auth'

// Mirrors the API: any well-formed link works once; the expired and used fixtures never work.
const UNUSABLE = new Set([RECOVERY_TOKENS.EXPIRED_RESET, RECOVERY_TOKENS.USED_RESET])
const usedTokens = new Set()

export function isResetLinkUsable(token) {
  return typeof token === 'string' && token !== '' && !UNUSABLE.has(token) && !usedTokens.has(token)
}

export function markResetLinkUsed(token) {
  usedTokens.add(token)
}

export function resetPasswordResetLinks() {
  usedTokens.clear()
}
