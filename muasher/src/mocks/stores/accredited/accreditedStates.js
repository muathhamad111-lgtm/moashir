export const STATES = {
  AWAITING_PAYMENT: 'awaiting-payment',
  DRAFT: 'draft',
  SUBMITTED: 'submitted',
  ASSIGNED: 'assigned',
  UNDER_REVIEW: 'under-review',
  NEEDS_COMPLETION: 'needs-completion',
  COMPLETED: 'completed',
  REFUNDED: 'refunded',
  DECLINED: 'declined-incomplete',
}

const CLOSED = [STATES.COMPLETED, STATES.REFUNDED, STATES.DECLINED]
const OUTSIDE_QUEUE = [STATES.AWAITING_PAYMENT, STATES.DRAFT]
const REVIEW_CLOCK = [STATES.SUBMITTED, STATES.ASSIGNED, STATES.UNDER_REVIEW]
const ENTITY_CONTENT = [STATES.DRAFT, STATES.NEEDS_COMPLETION]
const WITH_ASSESSOR = [STATES.ASSIGNED, STATES.UNDER_REVIEW, STATES.NEEDS_COMPLETION]
const REFUNDABLE = [STATES.DRAFT, STATES.SUBMITTED, STATES.ASSIGNED, STATES.UNDER_REVIEW, STATES.NEEDS_COMPLETION]

export const FORCE_TARGETS = [STATES.DRAFT, STATES.SUBMITTED, STATES.ASSIGNED, STATES.UNDER_REVIEW, STATES.COMPLETED]
export const FORCE_SOURCES_BLOCKED = [STATES.AWAITING_PAYMENT, STATES.REFUNDED, STATES.DECLINED]

export const QUEUE_STATES = Object.values(STATES).filter((state) => !OUTSIDE_QUEUE.includes(state))

export function isAccreditedOpen(status) {
  return !CLOSED.includes(status)
}

export function isInQueue(status) {
  return !OUTSIDE_QUEUE.includes(status)
}

export function runsReviewClock(status) {
  return REVIEW_CLOCK.includes(status)
}

export function acceptsEntityContent(status) {
  return ENTITY_CONTENT.includes(status)
}

export function hasAssessor(status) {
  return WITH_ASSESSOR.includes(status)
}

export function isRefundable(status) {
  return REFUNDABLE.includes(status)
}
