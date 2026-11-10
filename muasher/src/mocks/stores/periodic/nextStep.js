import { findAssessment } from '../assessments/assessmentsStore'
import { latestPaymentOf } from './awaitingPayment'
import { requestOfPayment } from './paidRequests'

const IN_REVIEW = ['submitted', 'assigned', 'under-review']
const OPEN_CARD_ORDER = ['created', 'pending']
const REJECTED = 'rejected'
const APPLY_STATUSES = ['not-submitted', 'closed-without-result']

const ROUTES = {
  self: { route: 'paths.self', href: '/paths/self' },
  accredited: { route: 'paths.accredited', href: '/paths/accredited' },
  award: { route: 'paths.award', href: '/paths/award' },
}

function target(path) {
  return { route: ROUTES[path].route, params: {}, query: {}, href: ROUTES[path].href }
}

function requestTarget(id) {
  return {
    route: 'paths.accredited.request',
    params: { id },
    query: { tab: 'request' },
    href: `/paths/accredited/${id}?tab=request`,
  }
}

function context(fields = {}) {
  return {
    assessmentId: null,
    number: null,
    status: null,
    paymentId: null,
    isPaid: null,
    deadlineAt: null,
    periodEndsOn: null,
    cycleId: null,
    paymentReference: null,
    paymentChannel: null,
    paymentStatus: null,
    paymentExpiresAt: null,
    ...fields,
  }
}

function step(rank, key, path, stepTarget, fields) {
  return { rank, key, path, target: stepTarget, context: context(fields) }
}

function requestStep(rank, key, request, extra = {}) {
  return step(rank, key, 'accredited', requestTarget(request.id), {
    assessmentId: request.id,
    number: request.number,
    status: request.status,
    isPaid: true,
    ...extra,
  })
}

function isOpenCard(payment) {
  return (
    (payment.channel ?? 'electronic') !== 'bank_transfer' &&
    OPEN_CARD_ORDER.includes(payment.status)
  )
}

function needsNewOrder(payment, path) {
  return (
    payment.status === REJECTED &&
    !requestOfPayment(payment.id) &&
    !path.openRequest &&
    !path.openDraft &&
    !path.awaitingPayment &&
    path.pricing.availability === 'available' &&
    !path.pricing.isFree
  )
}

function paymentCandidate(entityId, purpose, path, cycleId = null) {
  if (!path) return null
  const payment = latestPaymentOf(entityId, purpose, cycleId)
  if (!payment || !(isOpenCard(payment) || needsNewOrder(payment, path))) return null
  return { payment, purpose }
}

const STEP_PATHS = { self: 'self', accredited: 'accredited', award: 'advanced' }

function settleStep({ payment, purpose }) {
  const card = isOpenCard(payment)
  return step(1, 'settle_payment', STEP_PATHS[purpose], target(purpose), {
    paymentId: payment.id,
    isPaid: false,
    cycleId: payment.awardCycleId ?? null,
    paymentReference: payment.reference,
    paymentChannel: payment.channel ?? 'electronic',
    paymentStatus: payment.status,
    paymentExpiresAt: card ? payment.expiresAt : null,
  })
}

function paymentStep(entityId, paths) {
  const award = paths.award
  const awardOpen = award?.windowState === 'open' && !award.openRequest
  const [latest] = [
    paymentCandidate(entityId, 'accredited', paths.accredited),
    paymentCandidate(entityId, 'self', paths.self),
    awardOpen ? paymentCandidate(entityId, 'award', award, award.cycle?.id) : null,
  ]
    .filter(Boolean)
    .sort((left, right) => (left.payment.createdAt < right.payment.createdAt ? 1 : -1))
  return latest ? settleStep(latest) : null
}

function accreditedSteps({ accredited }) {
  const open = accredited.openRequest
  if (!open) return null
  const periodEndsOn = accredited.currentPeriod?.endsOn ?? null
  if (open.needsCompletion)
    return requestStep(2, 'complete_request', open, { deadlineAt: open.completionDeadline })
  if (open.status === 'draft') return requestStep(3, 'finish_draft', open, { periodEndsOn })
  return IN_REVIEW.includes(open.status) ? requestStep(4, 'track_review', open) : null
}

function paidSelfDraftStep({ self }) {
  const draft = self.openDraft
  if (!draft || !findAssessment(draft.id)?.paymentId) return null
  return step(3, 'finish_draft', 'self', target('self'), {
    assessmentId: draft.id,
    number: draft.number,
    status: draft.status,
    isPaid: true,
  })
}

function awardRequestStep({ award }) {
  const request = award.openRequest
  if (!request) return null
  const fields = { assessmentId: request.id, number: request.number, cycleId: award.cycle?.id }
  if (request.status === 'draft' && award.windowState === 'open')
    return step(3, 'finish_draft', 'advanced', target('award'), {
      ...fields,
      deadlineAt: award.cycle.submissionEnd,
    })
  return step(4, 'track_review', 'advanced', target('award'), fields)
}

function applySteps({ accredited, award, self }) {
  const canApply =
    accredited.isLive &&
    APPLY_STATUSES.includes(accredited.periodStatus) &&
    !accredited.openRequest &&
    !accredited.awaitingPayment &&
    accredited.pricing.availability === 'available'
  if (canApply)
    return step(7, 'apply_accredited', 'accredited', target('accredited'), {
      periodEndsOn: accredited.currentPeriod.endsOn,
    })
  if (award.windowState === 'open' && award.eligibility.eligible)
    return step(8, 'apply_award', 'advanced', target('award'), {
      cycleId: award.cycle.id,
      deadlineAt: award.cycle.submissionEnd,
    })
  if (self.openDraft)
    return paidSelfDraftStep({ self })
      ? null
      : step(9, 'continue_self_draft', 'self', target('self'), {
          assessmentId: self.openDraft.id,
          number: self.openDraft.number,
        })
  return self.pricing.availability === 'hidden' || self.awaitingPayment
    ? null
    : step(10, 'start_self', 'self', target('self'))
}

export function nextStepOf(paths, entityId) {
  const payment = paymentStep(Number(entityId), paths)
  if (payment) return payment
  const early = [accreditedSteps(paths), awardRequestStep(paths), paidSelfDraftStep(paths)]
    .filter(Boolean)
    .sort((left, right) => left.rank - right.rank)
  return early[0] ?? applySteps(paths)
}
