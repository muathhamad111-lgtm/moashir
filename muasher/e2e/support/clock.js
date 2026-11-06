export const MOCK_REFERENCE_NOW = new Date('2026-11-05T12:00:00+03:00')

export function pinMockClock(page) {
  return page.clock.install({ time: MOCK_REFERENCE_NOW })
}
