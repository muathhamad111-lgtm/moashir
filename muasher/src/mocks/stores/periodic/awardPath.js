import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { allAssessments, exposesResult } from '../assessments/assessmentsStore'
import { assessmentResource } from '../assessments/selfSerializers'
import { findEntity } from '../entitiesStore'
import { findRegion } from '../regionsStore'
import { findPayment } from '../finance/financeState'
import { quoteOfPrice } from '../finance/priceVersions'
import { requestPaymentOf } from '../accredited/requestPayments'
import { runningCycle } from '../award/cycleStore'
import { eligibilityOf, failuresOf } from '../award/eligibility'
import {
  awardAvailability,
  awardPricingOf,
  entryFeeOf,
  nextCycleOf,
  windowStateOf,
} from '../award/awardPricing'
import { openOrderSummaryOf } from './awaitingPayment'
import { isListed } from './pathListing'

const REJECTED = 'rejected-conditions'

function cycleResource(cycle) {
  return {
    id: cycle.id,
    name: { ...cycle.name },
    submissionStart: cycle.submissionStart,
    submissionEnd: cycle.submissionEnd,
    announcementAt: cycle.announcementAt ?? null,
    minEntityAgeYears: cycle.minEntityAgeYears,
    regions: cycle.regionIds
      .map(findRegion)
      .filter(Boolean)
      .map((region) => ({ id: region.id, code: region.code, name: { ...region.name } })),
    entryFee: quoteOfPrice(entryFeeOf(cycle)),
  }
}

function nextCycleResource(cycle) {
  return cycle
    ? {
        id: cycle.id,
        name: { ...cycle.name },
        submissionStart: cycle.submissionStart,
        entryFee: quoteOfPrice(entryFeeOf(cycle)),
      }
    : null
}

function advancedRecords(entityId) {
  return allAssessments().filter(
    (record) => record.path === ASSESSMENT_PATHS.ADVANCED && record.entityId === Number(entityId),
  )
}

function requestOf(records, cycle) {
  if (!cycle) return null
  return (
    records.find((record) => record.awardCycleId === cycle.id && record.status !== REJECTED) ?? null
  )
}

function eligibilityReason({ windowState, eligibility, request, awaitingPayment }) {
  if (windowState === 'none') return 'no_cycle'
  if (windowState === 'upcoming') return 'window_not_open'
  if (windowState === 'closed') return 'window_closed'
  if (request) return 'request_exists'
  if (awaitingPayment) return 'awaiting_payment'
  if (awardAvailability() !== 'available') return 'path_unavailable'
  return failuresOf(eligibility)[0]?.rule ?? null
}

function paidAfterWindow(request, cycle) {
  const payment = request?.paymentId ? findPayment(request.paymentId) : null
  return Boolean(payment?.paidAt && cycle && payment.paidAt > cycle.submissionEnd)
}

function openRequestOf(request) {
  const resource = request ? assessmentResource(request) : null
  if (!resource?.isOpen) return null
  return {
    id: resource.id,
    number: resource.number,
    status: resource.status,
    isOpen: resource.isOpen,
    payment: requestPaymentOf(request),
  }
}

function lastResultOf(request, cycle, announced) {
  if (!announced || !request || !exposesResult(request)) return null
  return {
    assessmentId: request.id,
    number: request.number,
    percent: assessmentResource(request).result?.percent ?? null,
    announcedAt: cycle.announcedAt,
  }
}

export function awardPathOf(entityId, now = Date.now()) {
  const cycle = runningCycle()
  const windowState = windowStateOf(cycle, now)
  const entity = findEntity(entityId)
  const records = advancedRecords(entityId)
  const request = requestOf(records, cycle)
  const awaitingPayment = cycle ? openOrderSummaryOf(Number(entityId), 'award', cycle.id) : null
  const eligibility = entity && cycle ? eligibilityOf(entity, cycle, now) : null
  const reason = eligibility
    ? eligibilityReason({ windowState, eligibility, request, awaitingPayment })
    : 'no_cycle'
  const announced = Boolean(cycle?.announcedAt)
  const pricing = awardPricingOf(cycle, now)
  return {
    pricing,
    isListed: isListed(pricing, records.length > 0, awaitingPayment),
    windowState,
    cycle: cycle ? cycleResource(cycle) : null,
    nextCycle: windowState === 'closed' ? nextCycleResource(nextCycleOf(cycle, now)) : null,
    eligibility: { eligible: reason === null, reason },
    awaitingPayment,
    openRequest: openRequestOf(request),
    windowClosedBeforePayment: paidAfterWindow(request, cycle),
    announced,
    lastResult: lastResultOf(request, cycle, announced),
  }
}
