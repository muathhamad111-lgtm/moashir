import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { riyadhDateKey } from '@/utils/datetime'
import { certificates } from '../certificatesStore'
import { accreditedOf } from '../accredited/accreditedRecords'
import { isAccreditedOpen } from '../accredited/accreditedStates'
import { findEntity } from '../entitiesStore'
import { pathLabelOf } from '../../fixtures/pathLabels'
import {
  PERIODIC_SETTINGS,
  currentPeriod,
  firstPeriod,
  followingPeriod,
  pastPeriods,
  periodOfInstant,
} from './periodCalendar'

const COMPLETED = 'completed'
const CLOSED_WITHOUT_RESULT = ['refunded', 'declined-incomplete', 'declined']
const OPEN_STAGES = ['submitted', 'assigned', 'under-review', 'needs-completion']
const PRE_PERIODIC_LABEL = { ar: 'قبل التقييم الدوري', en: 'Before periodic assessment' }
const PENDING_LABEL = { ar: 'تُحدَّد عند الإرسال', en: 'Set on submission' }
const KIT_SEED = { 'ACC-2026-000001': 'active' }

const NO_PERIOD = { periodState: null, period: null, periodLabel: null }

export function requestPeriodOf(record) {
  if (record.path !== ASSESSMENT_PATHS.ACCREDITED) return NO_PERIOD
  if (!record.submittedAt) {
    return isAccreditedOpen(record.status)
      ? { periodState: 'pending', period: null, periodLabel: PENDING_LABEL }
      : { periodState: 'none', period: null, periodLabel: null }
  }
  if (riyadhDateKey(record.submittedAt) < PERIODIC_SETTINGS.rolloutFrom)
    return { periodState: 'pre_periodic', period: null, periodLabel: PRE_PERIODIC_LABEL }
  const period = periodOfInstant(record.submittedAt)
  return { periodState: 'assigned', period, periodLabel: period.label }
}

function validUntilOf(record) {
  const { period } = requestPeriodOf(record)
  return (period ? followingPeriod(period) : firstPeriod()).endsOn
}

export function certificateOf(record) {
  const certificate = certificates.find(
    (item) => item.request?.id === record.id && item.status !== 'superseded',
  )
  if (!certificate) return null
  const validUntil = validUntilOf(record)
  const validUntilAt = new Date(`${validUntil}T23:59:59+03:00`).toISOString()
  return {
    id: certificate.id,
    number: certificate.number,
    level: certificate.level,
    status: certificate.status,
    issuedAt: certificate.issuedAt,
    validUntil,
    validUntilAt,
    isExpired: certificate.status === 'issued' && Date.now() >= Date.parse(validUntilAt),
  }
}

export function latestCompletedOf(entityId) {
  return (
    accreditedOf(entityId)
      .filter((record) => record.status === COMPLETED)
      .sort((left, right) => (left.completedAt < right.completedAt ? 1 : -1))[0] ?? null
  )
}

export function kitOf(record) {
  const latest = latestCompletedOf(record.entityId)
  if (!latest || latest.id !== record.id) return { status: null, requestId: null }
  return { status: KIT_SEED[record.number] ?? 'offered', requestId: null }
}

export function availableTabsOf(record) {
  if (record.path !== ASSESSMENT_PATHS.ACCREDITED) return { availableTabs: null, defaultTab: null }
  const completed = record.status === COMPLETED
  const tabs = ['request']
  if (completed) tabs.push('result', 'feedback')
  if (certificateOf(record)) tabs.push('certificate')
  if (completed) tabs.push('tools')
  if (['preparing', 'active', 'closed'].includes(kitOf(record).status)) tabs.push('kit')
  return { availableTabs: tabs, defaultTab: completed ? 'result' : 'request' }
}

export function periodStatusOf(entityId, period, now = new Date()) {
  const inPeriod = accreditedOf(entityId).filter(
    (record) => record.submittedAt && requestPeriodOf(record).period?.key === period.key,
  )
  const decisive =
    inPeriod.find((record) => record.status === COMPLETED) ??
    inPeriod.find((record) => OPEN_STAGES.includes(record.status)) ??
    inPeriod.find((record) => CLOSED_WITHOUT_RESULT.includes(record.status)) ??
    null
  if (decisive?.status === COMPLETED) return { status: 'assessed', request: decisive }
  if (decisive && OPEN_STAGES.includes(decisive.status))
    return { status: 'in-progress', request: decisive }
  if (decisive) return { status: 'closed-without-result', request: decisive }
  const isCurrent = currentPeriod(now)?.key === period.key
  return { status: isCurrent ? 'not-submitted' : 'missed', request: null }
}

function trackedFrom(entityId) {
  const verifiedAt = findEntity(entityId)?.verifiedAt
  return verifiedAt ? periodOfInstant(verifiedAt).startsOn : PERIODIC_SETTINGS.rolloutFrom
}

export function missedRowsOf(entityId, now = new Date()) {
  const from = trackedFrom(entityId)
  return pastPeriods(now)
    .filter((period) => period.startsOn >= from)
    .filter((period) => periodStatusOf(entityId, period, now).status === 'missed')
    .map((period) => ({
      rowType: 'missed_period',
      rowKey: `period-${period.id}`,
      id: null,
      number: null,
      path: ASSESSMENT_PATHS.ACCREDITED,
      pathLabel: pathLabelOf(ASSESSMENT_PATHS.ACCREDITED),
      status: null,
      periodStatus: 'missed',
      periodState: 'assigned',
      period,
      periodLabel: period.label,
      sortAt: period.endsAt,
    }))
}

const EMPTY_ROW_FIELDS = { payment: null, level: null, certificate: null, kit: null }

export function pathRowFieldsOf(record, presentPayment) {
  const base = { rowType: 'request', rowKey: `request-${record.id}`, ...requestPeriodOf(record) }
  if (record.path !== ASSESSMENT_PATHS.ACCREDITED)
    return { ...base, ...EMPTY_ROW_FIELDS, payment: presentPayment(record) }
  const certificate = certificateOf(record)
  return {
    ...base,
    payment: presentPayment(record),
    level: certificate?.level ?? null,
    certificate,
    kit: kitOf(record),
  }
}
