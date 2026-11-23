import { CYCLE_FILTERING_STATUSES, CYCLE_STATUSES } from '@/constants/advanced'

export const FILTERING_WINDOW = Object.freeze({ BEFORE: 'before', OPEN: 'open', ENDED: 'ended' })

// Decisions open with submissions and end when the results are approved (operator, 2026-11-22).
export function filteringWindowOf(status) {
  if (!status || CYCLE_FILTERING_STATUSES.includes(status)) return FILTERING_WINDOW.OPEN
  return status === CYCLE_STATUSES.DRAFT ? FILTERING_WINDOW.BEFORE : FILTERING_WINDOW.ENDED
}
