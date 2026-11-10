import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { allAssessments } from '../assessments/assessmentsStore'
import { assessmentResource, scoresResource } from '../assessments/selfSerializers'
import { accreditedOf, openCompletionOf } from '../accredited/accreditedRecords'
import { isAccreditedOpen } from '../accredited/accreditedStates'
import { accreditedProgressOf } from '../accredited/accreditedProgress'
import { accreditedPricing, periodicOf } from './accreditedPeriodic'
import { awaitingPaymentOf, openOrderSummaryOf } from './awaitingPayment'
import { awardPathOf } from './awardPath'
import { certificateOf, kitOf, latestCompletedOf, requestPeriodOf } from './requestPeriods'
import { pathPricingOf } from '../finance/priceVersions'
import { isListed } from './pathListing'
import { runAccreditedSchedule } from '../accredited/scheduler'

const NEEDS_COMPLETION = 'needs-completion'
const EDITABLE = ['draft', NEEDS_COMPLETION]

function selfRecords(entityId) {
  return allAssessments().filter(
    (record) => record.path === ASSESSMENT_PATHS.SELF && record.entityId === Number(entityId),
  )
}

function selfPathOf(entityId) {
  const records = selfRecords(entityId)
  const draft = records.find((record) => record.status === 'draft') ?? null
  const [last] = records
    .filter((record) => record.result)
    .sort((left, right) => (left.completedAt < right.completedAt ? 1 : -1))
  const progress = draft ? scoresResource(draft).progress : null
  const pricing = pathPricingOf('self')
  const awaitingPayment = openOrderSummaryOf(Number(entityId), 'self')
  return {
    pricing,
    isListed: isListed(pricing, records.length > 0, awaitingPayment),
    awaitingPayment,
    openDraft: draft
      ? {
          id: draft.id,
          number: draft.number,
          status: draft.status,
          updatedAt: draft.updatedAt,
          progress: { subsComplete: progress.scored, subsTotal: progress.required },
        }
      : null,
    lastResult: last
      ? {
          assessmentId: last.id,
          number: last.number,
          percent: assessmentResource(last).result?.percent ?? null,
          completedAt: last.completedAt,
        }
      : null,
  }
}

function openRequestOf(record) {
  if (!record) return null
  const { periodState, periodLabel } = requestPeriodOf(record)
  const needsCompletion = record.status === NEEDS_COMPLETION
  return {
    id: record.id,
    number: record.number,
    status: record.status,
    needsCompletion,
    completionDeadline: needsCompletion ? (openCompletionOf(record)?.deadlineAt ?? null) : null,
    remainingCount: EDITABLE.includes(record.status)
      ? (accreditedProgressOf(record)?.remaining.length ?? null)
      : null,
    submittedAt: record.submittedAt,
    periodState,
    periodLabel,
  }
}

function lastResultOf(record) {
  if (!record) return null
  const certificate = certificateOf(record)
  return {
    assessmentId: record.id,
    number: record.number,
    percent: assessmentResource(record).result?.percent ?? null,
    level: certificate?.level ?? null,
    completedAt: record.completedAt,
    ...requestPeriodOf(record),
    certificate,
  }
}

function accreditedPathOf(entityId) {
  const open = accreditedOf(entityId).find((record) => isAccreditedOpen(record.status)) ?? null
  const latest = latestCompletedOf(entityId)
  const periodic = periodicOf(entityId)
  const pricing = accreditedPricing()
  const awaitingPayment = awaitingPaymentOf(Number(entityId))
  return {
    pricing,
    isListed: isListed(pricing, accreditedOf(entityId).length > 0, awaitingPayment),
    isLive: periodic.isLive,
    cadence: periodic.cadence,
    currentPeriod: periodic.currentPeriod,
    periodStatus: periodic.periodStatus,
    nextPeriod: periodic.nextPeriod,
    nextSubmissionFrom: periodic.nextSubmissionFrom,
    awaitingPayment,
    openRequest: openRequestOf(open),
    lastResult: lastResultOf(latest),
    kit: latest ? kitOf(latest) : { status: null, requestId: null },
  }
}

export function pathsWidgetOf(entityId) {
  runAccreditedSchedule()
  return {
    self: selfPathOf(entityId),
    accredited: accreditedPathOf(entityId),
    award: awardPathOf(entityId),
  }
}

export function levelWidgetOf(entityId) {
  const valid = accreditedOf(entityId)
    .filter((record) => record.status === 'completed')
    .sort((left, right) => (left.completedAt < right.completedAt ? 1 : -1))
    .map(certificateOf)
    .find((certificate) => certificate?.status === 'issued' && !certificate.isExpired)
  return { level: valid?.level ?? null, certificate: valid ?? null }
}
