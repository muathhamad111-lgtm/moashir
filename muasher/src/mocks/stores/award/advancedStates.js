export const ADVANCED_STATES = {
  REJECTED_CONDITIONS: 'rejected-conditions',
  DRAFT: 'draft',
  EXPIRED: 'expired',
  AWAITING_ASSIGNMENT: 'awaiting-review-assignment',
  NEEDS_COMPLETION: 'needs-completion',
  DECLINED: 'declined-incomplete',
  WITHDRAWN: 'withdrawn',
  ASSIGNED: 'assigned',
  UNDER_INITIAL_ASSESSMENT: 'under-initial-assessment',
  AWAITING_FILTERING: 'awaiting-filtering',
  NOT_QUALIFIED: 'not-qualified',
  QUALIFIED: 'qualified-for-committee',
}

export const ENTITY_PLACEHOLDER = 'under-review'

const CLOSED = [ADVANCED_STATES.REJECTED_CONDITIONS, ADVANCED_STATES.EXPIRED, ADVANCED_STATES.DECLINED, ADVANCED_STATES.WITHDRAWN, ADVANCED_STATES.NOT_QUALIFIED]
const OUTSIDE_QUEUE = [ADVANCED_STATES.REJECTED_CONDITIONS, ADVANCED_STATES.DRAFT, ADVANCED_STATES.EXPIRED]
const REVIEW_CLOCK = [ADVANCED_STATES.ASSIGNED, ADVANCED_STATES.UNDER_INITIAL_ASSESSMENT]

export const FILTERING_STATES = [ADVANCED_STATES.AWAITING_FILTERING, ADVANCED_STATES.QUALIFIED, ADVANCED_STATES.NOT_QUALIFIED]

export const ADVANCED_QUEUE_STATES = Object.values(ADVANCED_STATES).filter((state) => !OUTSIDE_QUEUE.includes(state))

export function isAdvancedOpen(status) {
  return !CLOSED.includes(status)
}

export function isInAdvancedQueue(status) {
  return !OUTSIDE_QUEUE.includes(status)
}

export function runsAdvancedClock(status) {
  return REVIEW_CLOCK.includes(status)
}

export function hasAdvancedAssessor(status) {
  return REVIEW_CLOCK.includes(status)
}

export function isWithdrawable(status) {
  return isInAdvancedQueue(status) && isAdvancedOpen(status)
}

export function isInFiltering(status) {
  return FILTERING_STATES.includes(status)
}
