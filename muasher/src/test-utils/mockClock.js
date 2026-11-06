import { vi } from 'vitest'

export const MOCK_REFERENCE_NOW = new Date('2026-11-05T12:00:00+03:00')

export function pinMockClock() {
  vi.useFakeTimers({ toFake: ['Date'], now: MOCK_REFERENCE_NOW, shouldAdvanceTime: true })
}

export function releaseMockClock() {
  vi.useRealTimers()
}
