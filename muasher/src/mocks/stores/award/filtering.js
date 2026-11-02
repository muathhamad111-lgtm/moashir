import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { paginate } from '../../contract/pagination'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { allAssessments, entityOf, isFrozen } from '../assessments/assessmentsStore'
import { snapshotResource } from '../assessments/selfSerializers'
import { displayPercent } from '../assessments/selfScoring'
import { ACTORS, transition, visibleActorId } from '../assessments/statusHistory'
import { entitySummary } from '../entitySerializers'
import { findRegion } from '../regionsStore'
import { staffNameOf } from '../accredited/staffDirectory'
import { nowIso } from '../accredited/accreditedRecords'
import { reviewResource, withFeedback } from '../accredited/accreditedSerializers'
import { isPositiveInteger, lengthError, throwIfErrors, trimmed } from '../accredited/accreditedValidation'
import { assertNotFrozen } from '../accredited/entityWorkflow'
import { ADVANCED_STATES, FILTERING_STATES } from './advancedStates'
import { awardReject } from './awardRejection'
import { announceFilteringDecision } from './awardNotices'

const FILTER_KEYS = ['filter[status]', 'filter[regionId]']
const SORTABLE = { initialPercent: (record) => percentOf(record), submittedAt: (record) => record.submittedAt }
const OPEN_FOR_DECISIONS = ['submission-closed', 'committee-assessment']
const NOTE_MAX = 1000
const OVERRIDE_MIN = 20
const REASON = { MIN: 10, MAX: 1000 }

let decisionSequence = 60000

function percentOf(record) {
  return record.result ? Number(displayPercent(record.result.percent)) : null
}

function meetsThreshold(record, cycle) {
  const percent = percentOf(record)
  return percent === null ? null : percent >= Number(cycle.disqualificationScore)
}

function sortFieldOf(raw) {
  const field = raw.replace(/^-/, '').replace(/_([a-z])/g, (match, letter) => letter.toUpperCase())
  return Object.hasOwn(SORTABLE, field) ? field : null
}

export function filteringFiltersFrom(query) {
  const errors = {}
  if ([...query.keys()].some((key) => key.startsWith('filter[') && !FILTER_KEYS.includes(key))) errors.filter = [fieldMessage('validation.invalid', {}, 'filter')]
  const status = query.get('filter[status]')
  if (status && !FILTERING_STATES.includes(status)) errors['filter.status'] = [fieldMessage('validation.invalid', {}, 'filter')]
  const regionId = query.get('filter[regionId]')
  if (regionId && !isPositiveInteger(regionId)) errors['filter.regionId'] = [fieldMessage('validation.integer', {}, 'filter')]
  const sort = query.get('sort')
  if (sort && !sortFieldOf(sort)) errors.sort = [fieldMessage('validation.invalid', {}, 'sort')]
  throwIfErrors(errors)
  return { status: status || null, regionId: regionId ? Number(regionId) : null, search: (query.get('search') ?? '').trim(), sort: sort || '-initial_percent' }
}

function matchesSearch(record, needle, normalize) {
  if (!needle) return true
  const entity = entityOf(record)
  const target = normalize(needle)
  return [record.number, entity.name.ar, entity.name.en].some((value) => normalize(value ?? '').includes(target))
}

function cycleRecords(cycle, filters, normalize) {
  return allAssessments()
    .filter((record) => record.awardCycleId === cycle.id && FILTERING_STATES.includes(record.status) && !isFrozen(record))
    .filter((record) => filters.regionId === null || record.regionId === filters.regionId)
    .filter((record) => matchesSearch(record, filters.search, normalize))
}

function compareBy(field, descending) {
  const valueOf = SORTABLE[field]
  return (left, right) => {
    const a = valueOf(left)
    const b = valueOf(right)
    if (a === b) return right.id - left.id
    if (a === null) return 1
    if (b === null) return -1
    return (a < b ? -1 : 1) * (descending ? -1 : 1)
  }
}

export function decisionResource(decision) {
  return {
    id: decision.id,
    outcome: decision.outcome,
    isOverride: decision.isOverride,
    initialPercent: decision.initialPercent,
    thresholdPercent: decision.thresholdPercent,
    reason: decision.reason,
    note: decision.note,
    decidedBy: { id: decision.deciderId, name: decision.deciderId === null ? null : staffNameOf(decision.deciderId) },
    decidedAt: decision.decidedAt,
  }
}

function initialResultOf(record) {
  if (!record.result) return null
  const { total, maxTotal, percent } = snapshotResource(record)
  return { total, maxTotal, percent }
}

export function filteringRow(record, cycle) {
  const region = record.regionId ? findRegion(record.regionId) : null
  const decision = record.filteringDecisions.at(-1)
  return {
    id: record.id,
    number: record.number,
    status: record.status,
    isFrozen: isFrozen(record),
    entity: entitySummary(entityOf(record)),
    region: region ? { id: region.id, code: region.code, name: { ...region.name } } : null,
    initialResult: initialResultOf(record),
    meetsThreshold: meetsThreshold(record, cycle),
    initialAssessedAt: record.initialAssessedAt,
    submittedAt: record.submittedAt,
    decision: decision ? decisionResource(decision) : null,
  }
}

export function filteringQueue(cycle, query, normalize) {
  const filters = filteringFiltersFrom(query)
  const unfiltered = cycleRecords(cycle, filters, normalize)
  const rows = unfiltered.filter((record) => !filters.status || record.status === filters.status).sort(compareBy(sortFieldOf(filters.sort), filters.sort.startsWith('-')))
  const { items, pagination } = paginate(rows, query)
  const count = (status) => unfiltered.filter((record) => record.status === status).length
  return {
    items: items.map((record) => filteringRow(record, cycle)),
    meta: {
      disqualificationScore: cycle.disqualificationScore,
      counts: {
        awaitingFiltering: count(ADVANCED_STATES.AWAITING_FILTERING),
        qualifiedForCommittee: count(ADVANCED_STATES.QUALIFIED),
        notQualified: count(ADVANCED_STATES.NOT_QUALIFIED),
      },
      belowThreshold: unfiltered.filter((record) => record.status === ADVANCED_STATES.AWAITING_FILTERING && meetsThreshold(record, cycle) === false).length,
      pagination,
    },
  }
}

function initialAssessorOf(record) {
  const assignment = [...record.assignments].reverse().find((item) => item.status === 'completed') ?? null
  return assignment ? { id: assignment.assessorId, name: staffNameOf(assignment.assessorId) } : null
}

export function filteringDetail(record, cycle) {
  return {
    row: filteringRow(record, cycle),
    disqualificationScore: cycle.disqualificationScore,
    assessor: initialAssessorOf(record),
    review: reviewResource(record, { mode: 'read_only', context: [] }),
    result: withFeedback(record, snapshotResource(record)),
    decisions: record.filteringDecisions.map(decisionResource),
  }
}

function enterGate(user, cycle, record, { verb, reason }) {
  assertNotFrozen(record)
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  if (!OPEN_FOR_DECISIONS.includes(cycle.status)) throw assessmentReject.coded(409, 'cycle.filtering_not_open')
  if (record.status !== ADVANCED_STATES.AWAITING_FILTERING) throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), verb)
  if (!user.superAdmin && initialAssessorOf(record)?.id === user.id) throw assessmentReject.coded(403, 'sod_conflict')
}

function noteOf(body, errors) {
  if (body.note !== undefined && body.note !== null && (typeof body.note !== 'string' || body.note.length > NOTE_MAX)) {
    errors.note = [fieldMessage('validation.max_chars', { max: NOTE_MAX }, 'note')]
  }
  return trimmed(body.note) || null
}

function decide(user, cycle, record, { outcome, reason, note, verb, isOverride }) {
  const at = nowIso()
  decisionSequence += 1
  const decision = {
    id: decisionSequence,
    outcome,
    isOverride,
    initialPercent: snapshotResource(record).percent,
    thresholdPercent: cycle.disqualificationScore,
    reason,
    note,
    deciderId: user.superAdmin ? null : user.id,
    decidedAt: at,
  }
  record.filteringDecisions.push(decision)
  const status = outcome === 'qualified' ? ADVANCED_STATES.QUALIFIED : ADVANCED_STATES.NOT_QUALIFIED
  record.transitions.push(transition(ADVANCED_STATES.AWAITING_FILTERING, status, verb, at, ACTORS.STAFF, visibleActorId(user)))
  Object.assign(record, { status, updatedAt: at })
  announceFilteringDecision(record, decision)
  return record
}

export function qualifyRequest(user, cycle, record, body) {
  const errors = {}
  const note = noteOf(body, errors)
  const hasOverride = trimmed(body.overrideReason) !== ''
  if (hasOverride) {
    const fault = lengthError(body.overrideReason, { min: OVERRIDE_MIN, max: REASON.MAX, attribute: 'overrideReason' })
    if (fault) errors.overrideReason = fault
  }
  if (trimmed(body.reason)) {
    const fault = lengthError(body.reason, { min: REASON.MIN, max: REASON.MAX, attribute: 'reason' })
    if (fault) errors.reason = fault
  }
  throwIfErrors(errors)
  const overrideReason = trimmed(body.overrideReason) || null
  enterGate(user, cycle, record, { verb: 'qualify', reason: trimmed(body.reason) || overrideReason })
  const below = meetsThreshold(record, cycle) === false
  if (below && !overrideReason) throw awardReject.overrideReasonRequired(snapshotResource(record).percent, cycle.disqualificationScore)
  return decide(user, cycle, record, { outcome: 'qualified', reason: below ? overrideReason : null, note, verb: 'qualify', isOverride: below })
}

export function disqualifyRequest(user, cycle, record, body) {
  const errors = {}
  const note = noteOf(body, errors)
  const fault = lengthError(body.reason, { min: REASON.MIN, max: REASON.MAX, attribute: 'reason' })
  if (fault) errors.reason = fault
  throwIfErrors(errors)
  enterGate(user, cycle, record, { verb: 'disqualify', reason: trimmed(body.reason) })
  return decide(user, cycle, record, { outcome: 'not_qualified', reason: trimmed(body.reason), note, verb: 'disqualify', isOverride: false })
}
