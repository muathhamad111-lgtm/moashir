import { ACCREDITED_STATUSES as ACC } from '@/constants/accredited'
import { AVAILABILITY, PERIOD_STATUSES, WINDOW_STATES } from '@/constants/entityPaths'

const EDITABLE = [ACC.DRAFT, ACC.NEEDS_COMPLETION]

export const SELF_STATES = Object.freeze({
  HIDDEN: 'hidden',
  UNAVAILABLE: 'unavailable',
  DRAFT: 'draft',
  AWAITING_PAYMENT: 'awaitingPayment',
  PAY_START: 'payStart',
  START: 'start',
})

export const ACCREDITED_STATES = Object.freeze({
  HIDDEN: 'hidden',
  AWAITING_PAYMENT: 'awaitingPayment',
  NEEDS_COMPLETION: 'needsCompletion',
  DRAFT: 'draft',
  IN_REVIEW: 'inReview',
  UNAVAILABLE: 'unavailable',
  ASSESSED: 'assessed',
  APPLY: 'apply',
})

export const AWARD_STATES = Object.freeze({
  HIDDEN: 'hidden',
  LATE_PAYMENT: 'latePayment',
  DRAFT: 'draft',
  SUBMITTED: 'submitted',
  ANNOUNCED: 'announced',
  AWAITING_PAYMENT: 'awaitingPayment',
  UNAVAILABLE: 'unavailable',
  NONE: 'none',
  UPCOMING: 'upcoming',
  CLOSED: 'closed',
  NOT_ELIGIBLE: 'notEligible',
  ELIGIBLE: 'eligible',
})

function availabilityOf(path) {
  return path?.pricing?.availability ?? AVAILABILITY.AVAILABLE
}

export function isHidden(path) {
  if (!path) return true
  if (path.isListed === false) return true
  return path.isListed === undefined && availabilityOf(path) === AVAILABILITY.HIDDEN
}

function isAvailable(path) {
  return availabilityOf(path) === AVAILABILITY.AVAILABLE
}

export function isPaidPath(path) {
  return Boolean(path?.pricing?.price) && path.pricing.isFree !== true
}

export function isFreePath(path) {
  return path?.pricing?.availability === AVAILABILITY.AVAILABLE && !isPaidPath(path)
}

export function selfStateOf(self) {
  if (isHidden(self)) return SELF_STATES.HIDDEN
  if (self.openDraft) return SELF_STATES.DRAFT
  if (self.awaitingPayment) return SELF_STATES.AWAITING_PAYMENT
  if (!isAvailable(self)) return SELF_STATES.UNAVAILABLE
  return isPaidPath(self) ? SELF_STATES.PAY_START : SELF_STATES.START
}

function openRequestState(request) {
  if (request.status === ACC.NEEDS_COMPLETION) return ACCREDITED_STATES.NEEDS_COMPLETION
  if (EDITABLE.includes(request.status)) return ACCREDITED_STATES.DRAFT
  return ACCREDITED_STATES.IN_REVIEW
}

export function accreditedStateOf(accredited) {
  if (isHidden(accredited)) return ACCREDITED_STATES.HIDDEN
  if (accredited.awaitingPayment) return ACCREDITED_STATES.AWAITING_PAYMENT
  if (accredited.openRequest) return openRequestState(accredited.openRequest)
  if (!isAvailable(accredited)) return ACCREDITED_STATES.UNAVAILABLE
  if (accredited.periodStatus === PERIOD_STATUSES.ASSESSED) return ACCREDITED_STATES.ASSESSED
  return ACCREDITED_STATES.APPLY
}

const WINDOW_TO_STATE = {
  [WINDOW_STATES.NONE]: AWARD_STATES.NONE,
  [WINDOW_STATES.UPCOMING]: AWARD_STATES.UPCOMING,
  [WINDOW_STATES.CLOSED]: AWARD_STATES.CLOSED,
}

export function awardStateOf(award) {
  if (isHidden(award)) return AWARD_STATES.HIDDEN
  const request = award.openRequest
  if (request && award.windowClosedBeforePayment) return AWARD_STATES.LATE_PAYMENT
  if (request?.status === ACC.DRAFT) return AWARD_STATES.DRAFT
  if (request) return AWARD_STATES.SUBMITTED
  if (award.announced && award.lastResult) return AWARD_STATES.ANNOUNCED
  if (award.awaitingPayment) return AWARD_STATES.AWAITING_PAYMENT
  if (!isAvailable(award)) return AWARD_STATES.UNAVAILABLE
  const windowState = WINDOW_TO_STATE[award.windowState]
  if (windowState) return windowState
  return award.eligibility?.eligible ? AWARD_STATES.ELIGIBLE : AWARD_STATES.NOT_ELIGIBLE
}
