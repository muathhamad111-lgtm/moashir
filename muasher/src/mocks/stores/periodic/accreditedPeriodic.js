import { pathPricingOf } from '../finance/priceVersions'
import { PERIODIC_SETTINGS, currentPeriod, isLive, nextPeriod } from './periodCalendar'
import { periodStatusOf } from './requestPeriods'
import { nextSubmissionGate } from './periodConflict'

const DRAFT = 'draft'

export function accreditedPricing() {
  return pathPricingOf('accredited')
}

export function periodicOf(entityId, now = new Date()) {
  const live = isLive(now)
  const period = currentPeriod(now)
  const state = period ? periodStatusOf(entityId, period, now) : null
  const next = nextPeriod(now)
  const assessed = state?.status === 'assessed'
  return {
    isLive: live,
    rolloutFrom: PERIODIC_SETTINGS.rolloutFrom,
    cadence: PERIODIC_SETTINGS.cadence,
    cadenceEffectiveFrom: null,
    currentPeriod: period,
    periodStatus: state?.status ?? null,
    periodRequest: state?.request
      ? { id: state.request.id, number: state.request.number, status: state.request.status }
      : null,
    nextPeriod: next,
    nextSubmissionFrom: assessed ? next.startsOn : null,
  }
}

function submissionReason({ active, awaitingPayment, entityFrozen }) {
  if (!active || active.status !== DRAFT) return 'no_open_draft'
  if (awaitingPayment) return 'awaiting_payment'
  if (entityFrozen) return 'entity_frozen'
  if (active.criteriaUpdatePendingSince) return 'criteria_update_in_progress'
  if (active.remaining > 0) return 'incomplete'
  return nextSubmissionGate(active.entityId) ? 'period_already_assessed' : null
}

export function submissionOf(context) {
  const reason = submissionReason(context)
  const gate = reason === 'period_already_assessed'
  return {
    canSubmitNow: reason === null,
    reason,
    nextSubmissionFrom: gate ? nextPeriod().startsOn : null,
  }
}

export function applyOf({ active, awaitingPayment, entityId }) {
  const pricing = accreditedPricing()
  const reason =
    (awaitingPayment && 'awaiting_payment') ||
    (active && 'open_request_exists') ||
    (pricing.availability !== 'available' && 'path_unavailable') ||
    null
  return {
    canStart: reason === null,
    reason,
    warning: nextSubmissionGate(entityId) ? 'period_already_assessed' : null,
  }
}
