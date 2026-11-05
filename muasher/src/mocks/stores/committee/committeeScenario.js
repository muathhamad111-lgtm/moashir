import { readCookie } from '../../contract/cookies'
import { versionTree } from '../criteriaSerializers'
import { findVersion } from '../criteriaStore'
import {
  computeSelfResult,
  displayPercent,
  parseRawHundredths,
  scorableUnits,
  unitKey,
} from '../assessments/selfScoring'
import { ACTORS, transition } from '../assessments/statusHistory'
import { addDays } from '../accredited/dates'
import {
  AWARD_CYCLE_IDS,
  AWARD_STAFF,
  ADVANCED_FIXTURE_IDS as IDS,
} from '../../fixtures/awardCycles'
import { COMMITTEE_SEAT_USERS, SCENARIO_PLANS } from '../../fixtures/committeeScenarios'
import { findCycle } from '../award/cycleStore'
import { ADVANCED_STATES as S } from '../award/advancedStates'
import { committeeState, insert, resetCommitteeState } from './committeeState'
import { applyBoard, applyDiscussion, averageOf } from './committeeScenarioBoard'

export const MOCK_SCENARIO_COOKIE = 'moashir_mock_scenario'

let scenarioOverride = null

export function setMockScenario(name) {
  scenarioOverride = name
}

export function activeScenario() {
  if (scenarioOverride !== null) return scenarioOverride
  return typeof document === 'undefined' ? null : readCookie(MOCK_SCENARIO_COOKIE)
}

function scoresFrom(tree, values, count = null) {
  const units = scorableUnits(tree)
  const scored = count === null ? units : units.slice(0, count)
  return Object.fromEntries(
    scored.map((unit, index) => [
      unitKey(unit.level, unit.id),
      parseRawHundredths(values[index % values.length]),
    ]),
  )
}

function shiftCycle(cycle, plan, anchor) {
  const dates = Object.fromEntries(
    Object.entries(plan.offsets).map(([field, offset]) => [
      field,
      offset === null ? null : addDays(anchor, offset),
    ]),
  )
  Object.assign(cycle, dates, {
    status: plan.status,
    submissionsClosedAt: dates.submissionEnd,
    committeeStartedAt: plan.committeeStarted ? dates.committeeStart : null,
    updatedAt: anchor,
  })
}

function completeInitialStage(record, tree, anchor) {
  if (record.result) return
  record.scores = scoresFrom(tree, ['8.00', '7.50', '8.50', '7.00'])
  record.result = {
    version: 1,
    criteriaVersionId: record.criteriaVersionId,
    scaleKind: record.scaleKind,
    ...computeSelfResult(tree, new Map(Object.entries(record.scores)), record.scaleKind),
  }
  record.completionRequests.forEach((completion) =>
    Object.assign(completion, { status: 'resolved', resolvedAt: addDays(anchor, -25) }),
  )
  record.assignments = [
    {
      id: record.id * 10,
      assessorId: 11,
      assignedById: AWARD_STAFF.follower,
      status: 'completed',
      reason: null,
      startedAt: addDays(anchor, -30),
      endedAt: addDays(anchor, -22),
      endReason: null,
      createdAt: addDays(anchor, -31),
    },
  ]
  record.initialAssessedAt = addDays(anchor, -22)
  record.dueAt = null
}

function qualify(record, anchor, plan) {
  if (record.status === S.NOT_QUALIFIED || plan.outcome === 'not_qualified') return
  record.filteringDecisions.push({
    id: record.id * 10 + 1,
    outcome: 'qualified',
    isOverride: false,
    initialPercent: displayPercent(record.result.percent),
    thresholdPercent: '60.00',
    reason: null,
    note: null,
    deciderId: AWARD_STAFF.filteringOfficer,
    decidedAt: addDays(anchor, -18),
  })
}

function moveTo(record, status, anchor) {
  record.transitions.push(
    transition(record.status, status, 'scenario', addDays(anchor, -1), ACTORS.SYSTEM),
  )
  Object.assign(record, { status, updatedAt: addDays(anchor, -1) })
}

function seatsOf(cycle, anchor) {
  return COMMITTEE_SEAT_USERS.map((userId) =>
    insert('seats', {
      cycleId: cycle.id,
      userId,
      status: 'active',
      replacesId: null,
      disabledAt: null,
      disabledReason: null,
      removedAt: null,
      createdAt: addDays(anchor, -15),
    }),
  )
}

function rowOf(seat, record, tree, spec, anchor) {
  const scores = spec.values ? scoresFrom(tree, spec.values, spec.count ?? null) : {}
  const completed = spec.status === 'completed'
  const percent = completed
    ? computeSelfResult(tree, new Map(Object.entries(scores)), record.scaleKind).percent
    : null
  return insert('assignments', {
    cycleId: seat.cycleId,
    seatId: seat.id,
    assessmentId: record.id,
    status: spec.status,
    replacesAssignmentId: null,
    substituteApprovedAt: null,
    scores,
    notes: spec.note ? { [String(tree.mains[0].subs[0].id)]: spec.note } : {},
    generalNote: spec.generalNote ?? null,
    percent,
    startedAt: spec.status === 'not-started' ? null : addDays(anchor, -8),
    submittedAt: completed ? addDays(anchor, -3) : null,
    returnedAt: null,
    voidedAt: null,
    voidReason: null,
    createdAt: addDays(anchor, -10),
  })
}

function applyRecordPlan(record, plan, seats, anchor) {
  const tree = versionTree(findVersion(record.criteriaVersionId))
  completeInitialStage(record, tree, anchor)
  qualify(record, anchor, plan)
  if (plan.outcome === 'not_qualified') return moveTo(record, S.NOT_QUALIFIED, anchor)
  const rows = (plan.rows ?? []).map((spec, index) =>
    rowOf(seats[index], record, tree, spec, anchor),
  )
  if (rows.length && rows.every((row) => row.status === 'completed'))
    record.committeeAverageId = averageOf(record, rows, anchor).id
  moveTo(record, plan.status, anchor)
  if (plan.discussion) applyDiscussion(record, seats, plan.discussion, anchor)
  return null
}

export function applyCommitteeScenario(records, anchor = new Date().toISOString()) {
  resetCommitteeState()
  const plan = SCENARIO_PLANS[activeScenario()]
  if (!plan) return
  const cycle = findCycle(AWARD_CYCLE_IDS.running)
  shiftCycle(cycle, plan.cycle, anchor)
  const seats = plan.seated ? seatsOf(cycle, anchor) : []
  Object.entries(plan.records).forEach(([key, recordPlan]) => {
    const record = records.find((item) => item.id === IDS[key])
    applyRecordPlan(record, recordPlan, seats, anchor)
  })
  applyBoard(cycle, records, plan, anchor, committeeState.averages)
}
