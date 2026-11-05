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
  UNDER_COMMITTEE: 'under-committee-assessment',
  COMMITTEE_DISCUSSION: 'committee-discussion',
  AWAITING_SECRETARY: 'awaiting-secretary-approval',
  RETURNED_TO_COMMITTEE: 'returned-to-committee',
  APPROVED: 'approved',
  REGIONAL_WINNER: 'regional-winner',
  NON_WINNER: 'non-winner',
  QUALIFIED_FOR_NATIONAL: 'qualified-for-national',
  NATIONAL_WINNER: 'national-winner',
  NATIONAL_NON_WINNER: 'national-non-winner',
}

export const ENTITY_PLACEHOLDER = 'under-review'

const S = ADVANCED_STATES

const CLOSED = [
  S.REJECTED_CONDITIONS,
  S.EXPIRED,
  S.DECLINED,
  S.WITHDRAWN,
  S.NOT_QUALIFIED,
  S.NON_WINNER,
  S.NATIONAL_WINNER,
  S.NATIONAL_NON_WINNER,
]
const OUTSIDE_QUEUE = [S.REJECTED_CONDITIONS, S.DRAFT, S.EXPIRED]
const REVIEW_CLOCK = [S.ASSIGNED, S.UNDER_INITIAL_ASSESSMENT]

export const COMMITTEE_STATES = [
  S.UNDER_COMMITTEE,
  S.COMMITTEE_DISCUSSION,
  S.AWAITING_SECRETARY,
  S.RETURNED_TO_COMMITTEE,
]
export const RESULT_STATES = [
  S.APPROVED,
  S.REGIONAL_WINNER,
  S.NON_WINNER,
  S.QUALIFIED_FOR_NATIONAL,
  S.NATIONAL_WINNER,
  S.NATIONAL_NON_WINNER,
]
export const NATIONAL_STATES = [S.QUALIFIED_FOR_NATIONAL, S.NATIONAL_WINNER, S.NATIONAL_NON_WINNER]
export const QUALIFIED_STATES = [S.QUALIFIED, ...COMMITTEE_STATES, ...RESULT_STATES]
export const FILTERING_STATES = [S.AWAITING_FILTERING, S.QUALIFIED, S.NOT_QUALIFIED]
export const FILTERING_VISIBLE_STATES = [S.AWAITING_FILTERING, S.NOT_QUALIFIED, ...QUALIFIED_STATES]
export const RESULT_EXPOSING_STATES = [
  S.NOT_QUALIFIED,
  S.NON_WINNER,
  S.NATIONAL_WINNER,
  S.NATIONAL_NON_WINNER,
]
export const PRECEDES_SECRETARY = [
  S.AWAITING_ASSIGNMENT,
  S.NEEDS_COMPLETION,
  S.ASSIGNED,
  S.UNDER_INITIAL_ASSESSMENT,
  S.AWAITING_FILTERING,
  S.QUALIFIED,
  S.UNDER_COMMITTEE,
  S.RETURNED_TO_COMMITTEE,
]

export const ADVANCED_QUEUE_STATES = Object.values(ADVANCED_STATES).filter(
  (state) => !OUTSIDE_QUEUE.includes(state),
)

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

export function isInCommittee(status) {
  return COMMITTEE_STATES.includes(status)
}

export function isResult(status) {
  return RESULT_STATES.includes(status)
}

export function isQualified(status) {
  return QUALIFIED_STATES.includes(status)
}

export function isWithdrawable(status) {
  return isInAdvancedQueue(status) && isAdvancedOpen(status) && !isResult(status)
}

export function isInFiltering(status) {
  return FILTERING_VISIBLE_STATES.includes(status)
}
