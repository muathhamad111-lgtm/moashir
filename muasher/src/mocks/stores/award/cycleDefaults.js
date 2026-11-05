export const CYCLE_DEFAULTS = {
  minEntityAgeYears: 3,
  disqualificationScore: '60.00',
  initialReviewSlaDays: 14,
}

export function cycleDefaultsResource() {
  return { ...CYCLE_DEFAULTS }
}
