const MINUTE_MS = 60 * 1000

export const SESSION_PROMPT = Object.freeze({
  EVERY_MS: 120 * MINUTE_MS,
  GRACE_MS: 15 * MINUTE_MS,
  CHECK_MS: 1000,
  MINUTE_MS,
})
