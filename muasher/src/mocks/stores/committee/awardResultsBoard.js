import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { MockRejection } from '../../contract/rejection'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { entityOf, findAssessment, isFrozen } from '../assessments/assessmentsStore'
import { entitySummary } from '../entitySerializers'
import {
  isPositiveInteger,
  optionalReason,
  reasonError,
  trimmed,
} from '../accredited/accreditedValidation'
import {
  ADVANCED_STATES as S,
  NATIONAL_STATES,
  RESULT_STATES,
  isAdvancedOpen,
  isInAdvancedQueue,
} from '../award/advancedStates'
import { insert, rankingOf, rankingsOfCycle, regionApprovalOf } from './committeeState'
import { COMMITTEE_STAGE, cycleRecords, moveRecord, nowIso } from './committeeLifecycle'
import { placeOfRegion } from './committeeEntity'
import { recordDecision } from './secretaryDesk'
import { requireReason } from './committeeAccess'
import { announceResults } from './committeeNotices'

const RANKS = [1, 2, 3]
const UNAPPROVE_MIN = 20
const RANK_FIELD = { regional: 'regionalRank', national: 'nationalRank' }

function rankingRecord(ranking) {
  return findAssessment(ranking.assessmentId)
}

function boardRows(cycle, states, regionId) {
  return rankingsOfCycle(cycle.id).filter((ranking) => {
    const record = rankingRecord(ranking)
    return (
      record &&
      states.includes(record.status) &&
      !isFrozen(record) &&
      (regionId === null || ranking.regionId === regionId)
    )
  })
}

function sortBoard(rows, field) {
  return [...rows].sort((left, right) => {
    if (field === RANK_FIELD.regional && left.regionId !== right.regionId)
      return left.regionId - right.regionId
    const a = left[field] ?? Number.MAX_SAFE_INTEGER
    const b = right[field] ?? Number.MAX_SAFE_INTEGER
    if (a !== b) return a - b
    if (left.averagePercent !== right.averagePercent)
      return Number(right.averagePercent) - Number(left.averagePercent)
    return left.id - right.id
  })
}

export function resultRowResource(ranking) {
  const record = rankingRecord(ranking)
  return {
    assessmentId: record.id,
    number: record.number,
    status: record.status,
    entity: entitySummary(entityOf(record)),
    region: placeOfRegion(ranking.regionId),
    averagePercent: ranking.averagePercent,
    regionalRank: ranking.regionalRank,
    nationalRank: ranking.nationalRank,
    approvedAt: ranking.createdAt,
  }
}

function unsettled(cycle, settled, regionId = null) {
  return cycleRecords(cycle).filter(
    (record) =>
      isAdvancedOpen(record.status) &&
      isInAdvancedQueue(record.status) &&
      !settled.includes(record.status) &&
      (regionId === null || record.regionId === regionId),
  )
}

export function boardMeta(cycle, stage) {
  const settled = stage === 'regional' ? RESULT_STATES : NATIONAL_STATES
  return {
    cycleStatus: cycle.status,
    regions: cycle.regionIds.map((regionId) => {
      const approval = regionApprovalOf(cycle.id, regionId)
      const rows = boardRows(cycle, RESULT_STATES, regionId)
      return {
        ...placeOfRegion(regionId),
        approved: Boolean(approval),
        approvedAt: approval?.approvedAt ?? null,
        total: rows.length,
        ranked: rows.filter((row) => row.regionalRank !== null).length,
        pending: unsettled(cycle, settled, regionId).length,
      }
    }),
    national: {
      approved: Boolean(cycle.nationalApprovedAt),
      approvedAt: cycle.nationalApprovedAt ?? null,
      announcedAt: cycle.announcedAt ?? null,
    },
  }
}

export function resultsBoard(cycle, stage, regionId = null) {
  const states = stage === 'regional' ? RESULT_STATES : NATIONAL_STATES
  const rows = sortBoard(boardRows(cycle, states, regionId), RANK_FIELD[stage])
  return { items: rows.map(resultRowResource), meta: boardMeta(cycle, stage) }
}

function validatedRank(body) {
  const errors = {}
  if (!('rank' in body) || (body.rank !== null && !RANKS.includes(Number(body.rank))))
    errors.rank = [fieldMessage('validation.between', { min: 1, max: 3 }, 'rank')]
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  if (Object.keys(errors).length) throw assessmentReject.validation(errors)
  return {
    rank: body.rank === null ? null : Number(body.rank),
    reason: trimmed(body.reason) || null,
  }
}

function rankTaken(holder) {
  return new MockRejection(422, 'rank.taken', {
    fields: { rank: [fieldMessage('rank.taken')] },
    meta: { assessmentId: holder.assessmentId },
  })
}

export function boundRanking(cycle, assessmentId) {
  const ranking = rankingOf(assessmentId)
  if (!ranking || ranking.cycleId !== cycle.id || isFrozen(findAssessment(assessmentId)))
    throw assessmentReject.notFound()
  return ranking
}

export function setRank(user, cycle, ranking, stage, body) {
  const { rank, reason } = validatedRank(body)
  requireReason(user, reason)
  const record = rankingRecord(ranking)
  if (cycle.nationalApprovedAt) throw assessmentReject.coded(409, 'award.national_approved')
  if (stage === 'regional') {
    if (regionApprovalOf(cycle.id, ranking.regionId))
      throw assessmentReject.coded(409, 'award.region_locked')
    if (record.status !== S.APPROVED)
      throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'rank')
  } else if (record.status !== S.QUALIFIED_FOR_NATIONAL)
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'rank')
  const field = RANK_FIELD[stage]
  const peers = rankingsOfCycle(cycle.id).filter(
    (other) =>
      other.id !== ranking.id && (stage === 'national' || other.regionId === ranking.regionId),
  )
  const holder = rank === null ? null : peers.find((other) => other[field] === rank)
  if (holder) throw rankTaken(holder)
  ranking[field] = rank
  return resultRowResource(ranking)
}

export function cycleRegion(cycle, regionId) {
  if (!isPositiveInteger(regionId) || !cycle.regionIds.includes(Number(regionId)))
    throw assessmentReject.notFound()
  return Number(regionId)
}

function assertResultsOpen(cycle, verb) {
  if (cycle.nationalApprovedAt) throw assessmentReject.coded(409, 'award.national_approved')
  if (!COMMITTEE_STAGE.includes(cycle.status))
    throw assessmentReject.illegalTransition(toCanonicalStatus(cycle.status), verb)
}

export function approveRegion(user, cycle, regionId, body) {
  requireReason(user, optionalReason(body))
  if (regionApprovalOf(cycle.id, regionId))
    throw assessmentReject.coded(409, 'award.region_approved')
  assertResultsOpen(cycle, 'approve_region')
  const pending = unsettled(cycle, RESULT_STATES, regionId)
  if (pending.length)
    throw new MockRejection(409, 'region.requests_pending', {
      meta: {
        pending: pending.map((record) => ({
          id: record.id,
          number: record.number,
          status: record.status,
        })),
      },
    })
  const rows = boardRows(cycle, [S.APPROVED], regionId)
  if (!rows.some((row) => row.regionalRank !== null))
    throw assessmentReject.coded(422, 'winners.none')
  const at = nowIso()
  rows.forEach((row) => {
    const record = rankingRecord(row)
    if (row.regionalRank === null)
      return moveRecord(record, S.NON_WINNER, 'approve_region', user, at)
    moveRecord(record, S.REGIONAL_WINNER, 'approve_region', user, at)
    return moveRecord(record, S.QUALIFIED_FOR_NATIONAL, 'qualify_national', user, at)
  })
  insert('regionApprovals', {
    cycleId: cycle.id,
    regionId,
    approvedAt: at,
    approvedById: user.superAdmin ? null : user.id,
    unapprovedAt: null,
    unapprovalReason: null,
  })
  recordDecision(user, cycle, null, 'approve_region', { regionId })
}

export function unapproveRegion(user, cycle, regionId, body) {
  const fault = reasonError(body.reason, { required: true, min: UNAPPROVE_MIN })
  if (fault) throw assessmentReject.validation({ reason: fault })
  const approval = regionApprovalOf(cycle.id, regionId)
  if (!approval) throw assessmentReject.coded(409, 'award.region_not_approved')
  if (cycle.nationalApprovedAt) throw assessmentReject.coded(409, 'award.national_approved')
  const at = nowIso()
  boardRows(cycle, [S.NON_WINNER, S.REGIONAL_WINNER, S.QUALIFIED_FOR_NATIONAL], regionId).forEach(
    (row) => {
      row.nationalRank = null
      moveRecord(rankingRecord(row), S.APPROVED, 'unapprove_region', user, at)
    },
  )
  Object.assign(approval, { unapprovedAt: at, unapprovalReason: trimmed(body.reason) })
  recordDecision(user, cycle, null, 'unapprove_region', { regionId, note: trimmed(body.reason) })
}

export function approveNational(user, cycle, body) {
  requireReason(user, optionalReason(body))
  assertResultsOpen(cycle, 'approve_national')
  const pending = unsettled(cycle, [S.QUALIFIED_FOR_NATIONAL])
  if (pending.length) {
    const regionsPending = [...new Set(pending.map((record) => record.regionId))]
    throw new MockRejection(409, 'regions.pending', { meta: { regionsPending } })
  }
  const rows = boardRows(cycle, [S.QUALIFIED_FOR_NATIONAL], null)
  if (!rows.some((row) => row.nationalRank !== null))
    throw assessmentReject.coded(422, 'winners.none')
  const at = nowIso()
  rows.forEach((row) =>
    moveRecord(
      rankingRecord(row),
      row.nationalRank === null ? S.NATIONAL_NON_WINNER : S.NATIONAL_WINNER,
      'approve_national',
      user,
      at,
    ),
  )
  Object.assign(cycle, { status: 'approved', nationalApprovedAt: at, updatedAt: at })
  recordDecision(user, cycle, null, 'approve_national')
}

export function announceCycle(user, cycle, body) {
  requireReason(user, optionalReason(body))
  if (cycle.status !== 'approved')
    throw assessmentReject.illegalTransition(toCanonicalStatus(cycle.status), 'announce')
  const at = nowIso()
  Object.assign(cycle, {
    status: 'announced',
    announcedAt: at,
    announcedById: user.superAdmin ? null : user.id,
    updatedAt: at,
  })
  recordDecision(user, cycle, null, 'announce')
  announceResults(cycle)
  return cycle
}
