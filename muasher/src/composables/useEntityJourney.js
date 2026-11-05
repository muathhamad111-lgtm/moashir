import { ACCREDITED_STATUSES as ACC } from '@/constants/accredited'
import { SELF_STATUSES as SELF } from '@/constants/assessment'
import { ACTION_ITEM_KEYS } from '@/constants/actionCenter'

export const JOURNEY_STATIONS = Object.freeze(['profile', 'self', 'accredited', 'certificate'])

export const STATION_STATES = Object.freeze({
  DONE: 'done',
  CURRENT: 'current',
  UPCOMING: 'upcoming',
})

export const NEXT_STEPS = Object.freeze({
  PAYMENT: 'payment',
  COMPLETION: 'completion',
  EVIDENCE: 'evidence',
  IN_REVIEW: 'inReview',
  SELF_DRAFT: 'selfDraft',
  SELF_SUBMITTED: 'selfSubmitted',
  SELF_START: 'selfStart',
  ACCREDITED_START: 'accreditedStart',
  CERTIFICATE: 'certificate',
})

const S = STATION_STATES
const ACTIVE_ENTITY = 'active'
const IN_REVIEW_STATES = [ACC.SUBMITTED, ACC.ASSIGNED, ACC.UNDER_REVIEW]
const RESTART_STATES = [ACC.REFUNDED, ACC.DECLINED]
const VALID_CERTIFICATES = ['issued', 'suspended']

function station(key, state, metaKey, params = {}) {
  return { key, state, meta: { key: metaKey, params } }
}

function byNewest(field) {
  return (left, right) => String(right[field] ?? '').localeCompare(String(left[field] ?? ''))
}

function facts(sources) {
  const self = sources.selfAssessments ?? []
  const accredited = sources.accredited ?? {}
  const certificates = (sources.certificates ?? []).filter((item) =>
    VALID_CERTIFICATES.includes(item.status),
  )
  return {
    entity: sources.entity ?? null,
    selfResult:
      self
        .filter((item) => item.status === SELF.RESULT_AVAILABLE)
        .sort(byNewest('completedAt'))[0] ?? null,
    selfDraft: self.find((item) => item.status === SELF.DRAFT) ?? null,
    selfSubmitted: self.find((item) => item.status === SELF.SUBMITTED) ?? null,
    hasSelf: self.length > 0,
    awaitingPayment: accredited.awaitingPayment ?? null,
    active: accredited.active ?? null,
    latestClosed: accredited.latestClosed ?? null,
    certificate: certificates.find((item) => item.status === 'issued') ?? certificates[0] ?? null,
    actionItems: sources.actionItems ?? [],
  }
}

function accreditedCompleted(fact) {
  return fact.latestClosed?.status === ACC.COMPLETED
}

function accreditedCurrent(fact) {
  return Boolean(fact.awaitingPayment || fact.active)
}

function profileStation(fact) {
  const verified = fact.entity?.status === ACTIVE_ENTITY && Boolean(fact.entity?.verifiedAt)
  return verified
    ? station('profile', S.DONE, 'verified', { date: fact.entity.verifiedAt })
    : station('profile', S.CURRENT, 'pending')
}

function selfStation(fact) {
  if (fact.selfResult) {
    const { result, completedAt } = fact.selfResult
    return station('self', S.DONE, 'result', {
      percent: result?.percent ?? null,
      date: completedAt,
    })
  }
  if (fact.selfDraft) return station('self', S.CURRENT, 'draft')
  if (fact.selfSubmitted) return station('self', S.CURRENT, 'submitted')
  if (!fact.hasSelf && !accreditedCurrent(fact)) return station('self', S.CURRENT, 'start')
  return station('self', S.UPCOMING, 'upcoming')
}

function activeAccreditedStation(fact) {
  const { active } = fact
  if (!active) return station('accredited', S.CURRENT, 'awaitingPayment')
  if (active.status === ACC.NEEDS_COMPLETION)
    return station('accredited', S.CURRENT, 'needsCompletion')
  if (IN_REVIEW_STATES.includes(active.status)) return station('accredited', S.CURRENT, 'inReview')
  const progress = active.progress
  return progress
    ? station('accredited', S.CURRENT, 'draft', {
        done: progress.subsComplete,
        total: progress.subsTotal,
      })
    : station('accredited', S.CURRENT, 'draftOpen')
}

function accreditedStation(fact) {
  if (accreditedCurrent(fact)) return activeAccreditedStation(fact)
  if (accreditedCompleted(fact))
    return station('accredited', S.DONE, 'completed', { date: fact.latestClosed.completedAt })
  const restart = RESTART_STATES.includes(fact.latestClosed?.status)
  return station('accredited', S.UPCOMING, restart ? 'restart' : 'upcoming')
}

function certificateStation(fact) {
  const { certificate } = fact
  if (certificate?.status === 'issued')
    return station('certificate', S.DONE, 'issued', { number: certificate.number })
  if (certificate) return station('certificate', S.DONE, 'underReview')
  if (accreditedCompleted(fact) && !accreditedCurrent(fact))
    return station('certificate', S.CURRENT, 'issuing')
  return station('certificate', S.UPCOMING, 'afterApproval')
}

function dueOf(fact, key) {
  return fact.actionItems.find((item) => item.key === key)?.dueAt ?? null
}

function requestStep(kind, request, extra = {}) {
  return { kind, assessmentId: request.id, reference: request.number, ...extra }
}

function accreditedStep(fact) {
  const { awaitingPayment, active } = fact
  if (awaitingPayment)
    return {
      kind: NEXT_STEPS.PAYMENT,
      paymentId: awaitingPayment.paymentId,
      reference: awaitingPayment.reference,
      expiresAt: awaitingPayment.expiresAt,
    }
  if (!active) return null
  if (active.status === ACC.NEEDS_COMPLETION)
    return requestStep(NEXT_STEPS.COMPLETION, active, {
      dueAt: dueOf(fact, ACTION_ITEM_KEYS.COMPLETE_ACCREDITED_REQUEST),
    })
  if (active.status === ACC.DRAFT)
    return requestStep(NEXT_STEPS.EVIDENCE, active, {
      progress: active.progress,
      updatedAt: active.updatedAt,
    })
  return requestStep(NEXT_STEPS.IN_REVIEW, active, { status: active.status })
}

function selfStep(fact) {
  if (fact.selfDraft) return { kind: NEXT_STEPS.SELF_DRAFT, assessmentId: fact.selfDraft.id }
  if (fact.selfSubmitted) return { kind: NEXT_STEPS.SELF_SUBMITTED }
  if (!fact.selfResult) return { kind: NEXT_STEPS.SELF_START }
  return null
}

function closingStep(fact) {
  if (accreditedCompleted(fact))
    return requestStep(NEXT_STEPS.CERTIFICATE, fact.latestClosed, {
      certificate: fact.certificate,
    })
  return {
    kind: NEXT_STEPS.ACCREDITED_START,
    restart: RESTART_STATES.includes(fact.latestClosed?.status),
  }
}

function nextStepOf(fact) {
  return accreditedStep(fact) ?? selfStep(fact) ?? closingStep(fact)
}

function currentNumberOf(stations) {
  const current = stations.findIndex((item) => item.state === S.CURRENT)
  if (current >= 0) return current + 1
  const upcoming = stations.findIndex((item) => item.state === S.UPCOMING)
  return upcoming >= 0 ? upcoming + 1 : stations.length
}

export function useEntityJourney(sources = {}) {
  const fact = facts(sources)
  const stations = [
    profileStation(fact),
    selfStation(fact),
    accreditedStation(fact),
    certificateStation(fact),
  ]
  const currentNumber = currentNumberOf(stations)
  return {
    stations,
    currentNumber,
    currentStation: stations[currentNumber - 1],
    isComplete: stations.every((item) => item.state === S.DONE),
    selfResult: fact.selfResult,
    nextStep: nextStepOf(fact),
  }
}
