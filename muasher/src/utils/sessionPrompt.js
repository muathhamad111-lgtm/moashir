import { SESSION_PROMPT } from '@/constants/session'

export function promptDueAt({ windowStartedAt, lastActivityAt, idleMinutes }) {
  const everyWindow = windowStartedAt + SESSION_PROMPT.EVERY_MS
  if (!idleMinutes) return everyWindow
  const beforeIdle =
    lastActivityAt + idleMinutes * SESSION_PROMPT.MINUTE_MS - SESSION_PROMPT.GRACE_MS
  return Math.min(everyWindow, beforeIdle)
}

export function countdownText(remainingMs) {
  const totalSeconds = Math.max(0, Math.ceil(remainingMs / 1000))
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}
