import { describe, expect, it } from 'vitest'
import { countdownText, promptDueAt } from '@/utils/sessionPrompt'

const MINUTE = 60 * 1000

describe('sessionPrompt', () => {
  it('asks every two hours of filling in', () => {
    expect(promptDueAt({ windowStartedAt: 0, lastActivityAt: 0, idleMinutes: null })).toBe(
      120 * MINUTE,
    )
  })

  it('asks 15 minutes before the server idle limit when that comes first', () => {
    expect(promptDueAt({ windowStartedAt: 0, lastActivityAt: 10 * MINUTE, idleMinutes: 60 })).toBe(
      55 * MINUTE,
    )
    expect(promptDueAt({ windowStartedAt: 0, lastActivityAt: 100 * MINUTE, idleMinutes: 60 })).toBe(
      120 * MINUTE,
    )
  })

  it('formats the remaining time as minutes and seconds', () => {
    expect(countdownText(15 * MINUTE)).toBe('15:00')
    expect(countdownText(61 * 1000 + 1)).toBe('01:02')
    expect(countdownText(-5)).toBe('00:00')
  })
})
