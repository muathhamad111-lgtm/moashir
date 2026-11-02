import {
  committeeCycleFixture,
  filteringResultsFixture,
  committeeAssessorsFixture,
  committeeScoresFixture,
  committeeAveragesFixture,
  assignableCommitteeAssessorsFixture,
} from '../fixtures/committee'

function cloneScore(item) {
  return {
    ...item,
    scores: { subs: { ...item.scores.subs }, points: { ...item.scores.points } },
    notes: item.notes.map((n) => ({ ...n })),
  }
}

export const committeeCycle = {
  ...committeeCycleFixture,
  name: { ...committeeCycleFixture.name },
  conditions: { ...committeeCycleFixture.conditions },
}

export const filteringResults = filteringResultsFixture.map((item) => ({
  ...item,
  entityName: { ...item.entityName },
}))

export const committeeAssessors = committeeAssessorsFixture.map((item) => ({ ...item, name: { ...item.name } }))

export const committeeScores = committeeScoresFixture.map(cloneScore)

export const committeeAverages = committeeAveragesFixture.map((item) => ({
  ...item,
  entityName: { ...item.entityName },
}))

export const assignableCommitteeAssessors = assignableCommitteeAssessorsFixture.map((item) => ({
  ...item,
  name: { ...item.name },
}))

export function findFilteringResult(assessmentId) {
  return filteringResults.find((item) => item.assessmentId === Number(assessmentId))
}

export function findCommitteeAssessor(committeeAssessorId) {
  return committeeAssessors.find((item) => item.id === committeeAssessorId)
}

export function findCommitteeAssessorScore(committeeAssessorId, assessmentId) {
  return committeeScores.find(
    (item) => item.committeeAssessorId === committeeAssessorId && item.assessmentId === Number(assessmentId),
  )
}

export function scoresForAssessment(assessmentId) {
  return committeeScores.filter((item) => item.assessmentId === Number(assessmentId))
}

export function scoresForCommitteeAssessor(committeeAssessorId) {
  return committeeScores.filter((item) => item.committeeAssessorId === committeeAssessorId)
}

export function findAverage(assessmentId) {
  return committeeAverages.find((item) => item.assessmentId === Number(assessmentId))
}

export function activeCommitteeAssessors() {
  return committeeAssessors.filter((j) => j.status !== 'excluded' && j.status !== 'disabled')
}

export function computeAverageFor(assessmentId) {
  const active = activeCommitteeAssessors()
  const totals = active
    .map((j) => findCommitteeAssessorScore(j.id, assessmentId))
    .filter((rec) => rec && rec.status === 'completed' && typeof rec.total === 'number')
    .map((rec) => rec.total)
  if (!totals.length) return null
  const sum = totals.reduce((acc, n) => acc + n, 0)
  return Math.round(sum / totals.length)
}

export function allActiveCommitteeAssessorsCompleted(assessmentId) {
  const active = activeCommitteeAssessors()
  if (!active.length) return false
  return active.every((j) => {
    const rec = findCommitteeAssessorScore(j.id, assessmentId)
    return rec && rec.status === 'completed'
  })
}

export function upsertCommitteeAssessorScore(record) {
  const existing = findCommitteeAssessorScore(record.committeeAssessorId, record.assessmentId)
  if (existing) {
    Object.assign(existing, record)
    return existing
  }
  const clone = cloneScore(record)
  committeeScores.push(clone)
  return clone
}

export function removeCommitteeAssessorScores(committeeAssessorId) {
  let removed = 0
  for (let i = committeeScores.length - 1; i >= 0; i -= 1) {
    if (committeeScores[i].committeeAssessorId === committeeAssessorId) {
      committeeScores.splice(i, 1)
      removed += 1
    }
  }
  return removed
}

let substituteSeed = 9450
export function nextSubstituteCommitteeAssessorId() {
  substituteSeed += 1
  return `jur-${substituteSeed}`
}

export function qualifiedAssessmentIds() {
  return filteringResults
    .filter((item) => item.filteringStatus === 'qualified')
    .map((item) => item.assessmentId)
}

export function assignmentLoad(committeeAssessorId) {
  return committeeScores.filter((item) => item.committeeAssessorId === committeeAssessorId).length
}

export function rosterRow(committeeAssessor) {
  const records = scoresForCommitteeAssessor(committeeAssessor.id)
  return {
    id: committeeAssessor.id,
    name: { ...committeeAssessor.name },
    status: committeeAssessor.status,
    assignmentCount: records.length,
    startedCount: records.filter((r) => r.status !== 'not-started').length,
    completedCount: records.filter((r) => r.status === 'completed').length,
    substituteFor: committeeAssessor.substituteFor ?? null,
    assignments: records.map((r) => {
      const filtering = findFilteringResult(r.assessmentId)
      return {
        assessmentId: r.assessmentId,
        entityName: filtering ? { ...filtering.entityName } : null,
        status: r.status,
      }
    }),
  }
}

export function availableCommitteeAssessors() {
  return assignableCommitteeAssessors.filter((candidate) => !findCommitteeAssessor(candidate.id))
}

export function addCommitteeAssessorToCommittee(candidate) {
  if (findCommitteeAssessor(candidate.id)) return findCommitteeAssessor(candidate.id)
  const committeeAssessor = { id: candidate.id, name: { ...candidate.name }, status: 'not-started' }
  committeeAssessors.push(committeeAssessor)
  for (const assessmentId of qualifiedAssessmentIds()) {
    upsertCommitteeAssessorScore({
      cycleId: committeeCycle.id,
      committeeAssessorId: committeeAssessor.id,
      assessmentId,
      status: 'not-started',
      total: null,
      scores: { subs: {}, points: {} },
      notes: [],
      completedAt: null,
    })
  }
  return committeeAssessor
}

export function removeCommitteeAssessorFromCommittee(committeeAssessorId) {
  const affected = [...new Set(scoresForCommitteeAssessor(committeeAssessorId).map((s) => s.assessmentId))]
  removeCommitteeAssessorScores(committeeAssessorId)
  const index = committeeAssessors.findIndex((j) => j.id === committeeAssessorId)
  if (index !== -1) committeeAssessors.splice(index, 1)
  return affected
}

export function reassignAssessment(fromCommitteeAssessorId, toCommitteeAssessorId, assessmentId) {
  const id = Number(assessmentId)
  const source = findCommitteeAssessorScore(fromCommitteeAssessorId, id)
  if (!source) return { moved: false }
  const target = findCommitteeAssessor(toCommitteeAssessorId)
  if (!target || fromCommitteeAssessorId === toCommitteeAssessorId) return { moved: false }
  removeAssignmentRecord(fromCommitteeAssessorId, id)
  removeAssignmentRecord(toCommitteeAssessorId, id)
  upsertCommitteeAssessorScore({
    cycleId: committeeCycle.id,
    committeeAssessorId: toCommitteeAssessorId,
    assessmentId: id,
    status: 'not-started',
    total: null,
    scores: { subs: {}, points: {} },
    notes: [],
    completedAt: null,
  })
  syncCommitteeAssessorStatus(fromCommitteeAssessorId)
  syncCommitteeAssessorStatus(toCommitteeAssessorId)
  return { moved: true, assessmentId: id, fromCommitteeAssessorId, toCommitteeAssessorId }
}

export function redistributeAssignments() {
  const active = activeCommitteeAssessors()
  if (!active.length) return { balanced: false }
  const targets = qualifiedAssessmentIds()
  for (const assessmentId of targets) {
    for (const committeeAssessor of active) {
      if (!findCommitteeAssessorScore(committeeAssessor.id, assessmentId)) {
        upsertCommitteeAssessorScore({
          cycleId: committeeCycle.id,
          committeeAssessorId: committeeAssessor.id,
          assessmentId,
          status: 'not-started',
          total: null,
          scores: { subs: {}, points: {} },
          notes: [],
          completedAt: null,
        })
      }
    }
  }
  active.forEach((committeeAssessor) => syncCommitteeAssessorStatus(committeeAssessor.id))
  return { balanced: true, assignmentsPerCommitteeAssessor: targets.length, committeeAssessorCount: active.length }
}

function removeAssignmentRecord(committeeAssessorId, assessmentId) {
  const id = Number(assessmentId)
  const index = committeeScores.findIndex((s) => s.committeeAssessorId === committeeAssessorId && s.assessmentId === id)
  if (index === -1) return false
  committeeScores.splice(index, 1)
  return true
}

function syncCommitteeAssessorStatus(committeeAssessorId) {
  const committeeAssessor = findCommitteeAssessor(committeeAssessorId)
  if (!committeeAssessor || committeeAssessor.status === 'excluded' || committeeAssessor.status === 'disabled') return
  const records = scoresForCommitteeAssessor(committeeAssessorId)
  if (!records.length) {
    committeeAssessor.status = 'not-started'
    return
  }
  if (records.every((r) => r.status === 'completed')) committeeAssessor.status = 'completed'
  else if (records.some((r) => r.status !== 'not-started')) committeeAssessor.status = 'in-progress'
  else committeeAssessor.status = 'not-started'
}
