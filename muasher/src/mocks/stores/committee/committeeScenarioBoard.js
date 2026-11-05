import { addDays } from '../accredited/dates'
import { divideHalfUp, displayPercent } from '../assessments/selfScoring'
import { AWARD_STAFF, ADVANCED_FIXTURE_IDS as IDS } from '../../fixtures/awardCycles'
import { RESULT_STATES } from '../award/advancedStates'
import { insert } from './committeeState'

export function averageOf(record, rows, anchor) {
  const mean = divideHalfUp(
    rows.reduce((sum, row) => sum + row.percent, 0),
    rows.length,
  )
  return insert('averages', {
    assessmentId: record.id,
    version: 1,
    percent: displayPercent(mean),
    memberCount: rows.length,
    isManual: false,
    inputsDigest: rows
      .map((row) => `${row.id}:${row.percent}`)
      .sort()
      .join('|'),
    inputs: rows.map((row) => ({ assignmentId: row.id, percent: displayPercent(row.percent) })),
    createdAt: addDays(anchor, -3),
  })
}

export function applyDiscussion(record, seats, plan, anchor) {
  const discussion = insert('discussions', {
    cycleId: record.awardCycleId,
    assessmentId: record.id,
    mode: plan.mode,
    status: 'open',
    deadlineAt: addDays(anchor, plan.deadlineOffset),
    agreedOutcome: null,
    openedById: AWARD_STAFF.secretary,
    summary: null,
    createdAt: addDays(anchor, -2),
    closedAt: null,
  })
  insert('entries', {
    discussionId: discussion.id,
    seatId: seats[1].id,
    kind: 'opinion',
    body: plan.opinion,
    createdAt: addDays(anchor, -1),
    supersededAt: null,
  })
}

function averagePercentOf(record, averages) {
  return averages.find((average) => average.id === record.committeeAverageId)?.percent ?? '0.00'
}

export function applyBoard(cycle, records, plan, anchor, averages = []) {
  Object.entries(plan.records).forEach(([key, recordPlan]) => {
    const record = records.find((item) => item.id === IDS[key])
    if (!RESULT_STATES.includes(record.status)) return
    insert('rankings', {
      cycleId: cycle.id,
      assessmentId: record.id,
      regionId: record.regionId,
      averagePercent: averagePercentOf(record, averages),
      regionalRank: recordPlan.regionalRank ?? null,
      nationalRank: recordPlan.nationalRank ?? null,
      createdAt: addDays(anchor, -4),
    })
  })
  ;(plan.approvedRegions ?? []).forEach((regionId) => {
    insert('regionApprovals', {
      cycleId: cycle.id,
      regionId,
      approvedAt: addDays(anchor, -3),
      approvedById: AWARD_STAFF.secretary,
      unapprovedAt: null,
      unapprovalReason: null,
    })
  })
  if (plan.nationalApproved) cycle.nationalApprovedAt = addDays(anchor, -2)
  if (plan.announced)
    Object.assign(cycle, { announcedAt: addDays(anchor, -1), announcedById: AWARD_STAFF.secretary })
}
