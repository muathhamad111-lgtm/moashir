import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { mainCriteriaFixture, subCriteriaFixture, guidingPointsFixture } from '../fixtures/criteria'
import { assessmentFixture } from '../fixtures/settings'
import {
  committeeCycle,
  filteringResults,
  committeeAssessors,
  committeeScores,
  committeeAverages,
  findFilteringResult,
  findCommitteeAssessor,
  findCommitteeAssessorScore,
  scoresForCommitteeAssessor,
  scoresForAssessment,
  findAverage,
  computeAverageFor,
  allActiveCommitteeAssessorsCompleted,
  upsertCommitteeAssessorScore,
  removeCommitteeAssessorScores,
  nextSubstituteCommitteeAssessorId,
  qualifiedAssessmentIds,
  rosterRow,
  availableCommitteeAssessors,
  addCommitteeAssessorToCommittee,
  removeCommitteeAssessorFromCommittee,
  reassignAssessment,
  redistributeAssignments,
} from '../stores/committeeStore'
import { failsRandomly } from '../contract/faults'

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}

const maybeFail = failsRandomly(0.06)

function scoringTree() {
  return mainCriteriaFixture
    .filter((main) => main.active)
    .sort((a, b) => a.order - b.order)
    .map((main) => ({
      id: main.id,
      name: main.name,
      weight: main.weight,
      order: main.order,
      description: main.description,
      subs: subCriteriaFixture
        .filter((sub) => sub.mainCriterionId === main.id && sub.active)
        .sort((a, b) => a.order - b.order)
        .map((sub) => {
          const isGuiding = sub.evaluationType === 'guiding-points'
          const points = isGuiding
            ? guidingPointsFixture
                .filter((point) => point.subCriterionId === sub.id && point.active)
                .sort((a, b) => a.order - b.order)
                .map((point) => ({
                  id: point.id,
                  description: point.description,
                  weight: point.weight,
                  order: point.order,
                  scaleKind: point.scaleKind || assessmentFixture.scale,
                }))
            : []
          return {
            id: sub.id,
            mainCriterionId: sub.mainCriterionId,
            name: sub.name,
            description: sub.description,
            weight: sub.weight,
            order: sub.order,
            evaluationType: sub.evaluationType,
            hasCv: sub.hasCv,
            scaleKind: isGuiding ? null : assessmentFixture.scale,
            points,
          }
        }),
    }))
}

function weightedTotal(scoreRecord) {
  const tree = scoringTree()
  const subs = tree.flatMap((m) => m.subs)
  let earned = 0
  let weight = 0
  for (const sub of subs) {
    weight += sub.weight
    const ratio = scoreRecord.scores.subs[sub.id]
    if (typeof ratio === 'number') earned += ratio * sub.weight
  }
  if (weight === 0) return 0
  return Math.round((earned / weight) * 100)
}

function committeeAssessorRow(item) {
  return { id: item.id, name: { ...item.name }, status: item.status }
}

function scoreRecordView(record) {
  return {
    cycleId: record.cycleId,
    committeeAssessorId: record.committeeAssessorId,
    assessmentId: record.assessmentId,
    status: record.status,
    total: record.total,
    scores: { subs: { ...record.scores.subs }, points: { ...record.scores.points } },
    notes: record.notes.map((n) => ({ ...n })),
    substituteFor: record.substituteFor ?? null,
    priorNotesVisible: record.priorNotesVisible ?? false,
    completedAt: record.completedAt ?? null,
  }
}

function averageView(item) {
  return {
    cycleId: item.cycleId,
    assessmentId: item.assessmentId,
    entityId: item.entityId,
    entityName: { ...item.entityName },
    average: item.average,
    committeeAssessorCount: item.committeeAssessorCount,
    completedCount: item.completedCount,
    secretaryStatus: item.secretaryStatus,
    computedAt: item.computedAt,
    approvedAt: item.approvedAt,
    returnedAt: item.returnedAt,
    generalNote: item.generalNote ? { ...item.generalNote } : null,
  }
}

function refreshAverage(assessmentId) {
  const average = findAverage(assessmentId)
  const value = computeAverageFor(assessmentId)
  if (!average) return value
  average.average = value
  average.completedCount = committeeScores.filter(
    (s) => s.assessmentId === Number(assessmentId) && s.status === 'completed',
  ).length
  average.committeeAssessorCount = committeeAssessors.filter(
    (j) => j.status !== 'excluded' && j.status !== 'disabled',
  ).length
  average.computedAt = new Date().toISOString()
  return value
}

export const committeeHandlers = [
  http.get('/api/v1/award/committee/:cycleId/committee-assessors', async ({ params }) => {
    await delay(320)
    if (maybeFail()) return errorResponse('Failed to load the assessors committee', 503)
    if (params.cycleId !== committeeCycle.id) return errorResponse('Cycle not found', 404)
    return envelope(committeeAssessors.map(committeeAssessorRow), {
      cycleName: { ...committeeCycle.name },
    })
  }),

  http.get(
    '/api/v1/award/committee/:cycleId/committee-assessors/:committeeAssessorId/assignments',
    async ({ params }) => {
      await delay(300)
      if (maybeFail()) return errorResponse('Failed to load the assignments', 503)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      const items = scoresForCommitteeAssessor(params.committeeAssessorId).map((rec) => {
        const filtering = findFilteringResult(rec.assessmentId)
        return {
          assessmentId: rec.assessmentId,
          entityName: filtering ? { ...filtering.entityName } : null,
          status: rec.status,
          total: rec.total,
        }
      })
      return envelope(items, { committeeAssessorStatus: committeeAssessor.status })
    },
  ),

  http.get(
    '/api/v1/award/committee/:cycleId/committee-assessors/:committeeAssessorId/score/:assessmentId',
    async ({ params }) => {
      await delay(340)
      if (maybeFail()) return errorResponse('Failed to load the score', 503)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      const record = findCommitteeAssessorScore(params.committeeAssessorId, params.assessmentId)
      const filtering = findFilteringResult(params.assessmentId)
      let prior = null
      if (record?.substituteFor) {
        const priorRecord = committeeScores.find(
          (s) =>
            s.committeeAssessorId === record.substituteFor &&
            s.assessmentId === Number(params.assessmentId),
        )
        if (priorRecord) {
          prior = {
            scores: {
              subs: { ...priorRecord.scores.subs },
              points: { ...priorRecord.scores.points },
            },
            notes: record.priorNotesVisible ? priorRecord.notes.map((n) => ({ ...n })) : [],
            total: priorRecord.total,
            viewOnly: true,
          }
        }
      }
      return envelope({
        committeeAssessorStatus: committeeAssessor.status,
        entityName: filtering ? { ...filtering.entityName } : null,
        scaleKind: assessmentFixture.scale,
        tree: scoringTree(),
        record: record ? scoreRecordView(record) : null,
        prior,
      })
    },
  ),

  http.put(
    '/api/v1/award/committee/:cycleId/committee-assessors/:committeeAssessorId/score/:assessmentId/sub',
    async ({ request, params }) => {
      await delay(220)
      if (maybeFail(0.05)) return errorResponse('Could not save the score', 500)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      if (committeeAssessor.status === 'disabled' || committeeAssessor.status === 'excluded') {
        return errorResponse('The assessor is no longer active', 422)
      }
      const record = ensureRecord(params.committeeAssessorId, params.assessmentId)
      const body = await request.json()
      const subId = Number(body.subCriterionId)
      const ratio = Math.min(Math.max(Number(body.ratio) || 0, 0), 1)
      record.scores.subs[subId] = ratio
      if (committeeAssessor.status === 'not-started') committeeAssessor.status = 'in-progress'
      if (record.status === 'not-started') record.status = 'in-progress'
      return envelope({ subCriterionId: subId, ratio })
    },
  ),

  http.put(
    '/api/v1/award/committee/:cycleId/committee-assessors/:committeeAssessorId/score/:assessmentId/point',
    async ({ request, params }) => {
      await delay(220)
      if (maybeFail(0.05)) return errorResponse('Could not save the score', 500)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      if (committeeAssessor.status === 'disabled' || committeeAssessor.status === 'excluded') {
        return errorResponse('The assessor is no longer active', 422)
      }
      const record = ensureRecord(params.committeeAssessorId, params.assessmentId)
      const body = await request.json()
      const pointId = Number(body.pointId)
      const ratio = Math.min(Math.max(Number(body.ratio) || 0, 0), 1)
      record.scores.points[pointId] = ratio
      if (committeeAssessor.status === 'not-started') committeeAssessor.status = 'in-progress'
      if (record.status === 'not-started') record.status = 'in-progress'
      return envelope({ pointId, ratio })
    },
  ),

  http.put(
    '/api/v1/award/committee/:cycleId/committee-assessors/:committeeAssessorId/score/:assessmentId/notes',
    async ({ request, params }) => {
      await delay(260)
      if (maybeFail(0.05)) return errorResponse('Could not save the note', 500)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      if (committeeAssessor.status === 'disabled' || committeeAssessor.status === 'excluded') {
        return errorResponse('The assessor is no longer active', 422)
      }
      const record = ensureRecord(params.committeeAssessorId, params.assessmentId)
      const body = await request.json()
      const subId = Number(body.subCriterionId)
      const text = (body.text ?? '').toString()
      const existing = record.notes.find((n) => n.subCriterionId === subId)
      if (existing) existing.text = text
      else record.notes.push({ subCriterionId: subId, text })
      return envelope({ subCriterionId: subId, text })
    },
  ),

  http.post(
    '/api/v1/award/committee/:cycleId/committee-assessors/:committeeAssessorId/score/:assessmentId/complete',
    async ({ params }) => {
      await delay(420)
      if (maybeFail()) return errorResponse('Could not complete the scoring', 500)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      if (committeeAssessor.status === 'disabled' || committeeAssessor.status === 'excluded') {
        return errorResponse('The assessor is no longer active', 422)
      }
      const record = ensureRecord(params.committeeAssessorId, params.assessmentId)
      record.total = weightedTotal(record)
      record.status = 'completed'
      record.completedAt = new Date().toISOString()
      const mine = scoresForCommitteeAssessor(params.committeeAssessorId)
      if (mine.every((r) => r.status === 'completed')) committeeAssessor.status = 'completed'
      let averaged = null
      if (allActiveCommitteeAssessorsCompleted(params.assessmentId)) {
        averaged = ensureAverage(params.assessmentId)
        refreshAverage(params.assessmentId)
        averaged.secretaryStatus = 'pending'
      }
      return envelope({
        committeeAssessorId: record.committeeAssessorId,
        assessmentId: record.assessmentId,
        status: record.status,
        total: record.total,
        averageReady: Boolean(averaged),
      })
    },
  ),

  http.post(
    '/api/v1/award/committee/:cycleId/committee-assessors/:committeeAssessorId/disable',
    async ({ request, params }) => {
      await delay(460)
      if (maybeFail()) return errorResponse('Could not disable the assessor', 500)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      const body = await request.json()
      const mode = body.mode === 'substitute' ? 'substitute' : 'exclude'

      if (mode === 'exclude') {
        const affected = [
          ...new Set(
            scoresForCommitteeAssessor(params.committeeAssessorId).map((s) => s.assessmentId),
          ),
        ]
        removeCommitteeAssessorScores(params.committeeAssessorId)
        committeeAssessor.status = 'excluded'
        const recomputed = affected.map((assessmentId) => {
          const average = ensureAverage(assessmentId)
          const value = refreshAverage(assessmentId)
          average.secretaryStatus = 'pending'
          return {
            assessmentId,
            average: value,
            allCompleted: allActiveCommitteeAssessorsCompleted(assessmentId),
          }
        })
        return envelope({
          committeeAssessorId: committeeAssessor.id,
          mode,
          status: committeeAssessor.status,
          recomputed,
        })
      }

      committeeAssessor.status = 'disabled'
      const priorNotesVisible = body.priorNotesVisible !== false
      const substituteId = body.substituteCommitteeAssessorId ?? nextSubstituteCommitteeAssessorId()
      if (!findCommitteeAssessor(substituteId)) {
        committeeAssessors.push({
          id: substituteId,
          name: body.substituteName
            ? { ...body.substituteName }
            : { ar: 'مقيّم بديل', en: 'Substitute assessor' },
          status: 'not-started',
          substituteFor: committeeAssessor.id,
        })
      }
      const affected = [
        ...new Set(
          scoresForCommitteeAssessor(params.committeeAssessorId).map((s) => s.assessmentId),
        ),
      ]
      for (const assessmentId of affected) {
        upsertCommitteeAssessorScore({
          cycleId: committeeCycle.id,
          committeeAssessorId: substituteId,
          assessmentId,
          status: 'not-started',
          total: null,
          scores: { subs: {}, points: {} },
          notes: [],
          substituteFor: committeeAssessor.id,
          priorNotesVisible,
          completedAt: null,
        })
      }
      return envelope({
        committeeAssessorId: committeeAssessor.id,
        mode,
        status: committeeAssessor.status,
        substituteCommitteeAssessorId: substituteId,
        priorNotesVisible,
        affected,
      })
    },
  ),

  http.get('/api/v1/award/committee/:cycleId/roster', async ({ params }) => {
    await delay(320)
    if (maybeFail()) return errorResponse('Failed to load the roster', 503)
    if (params.cycleId !== committeeCycle.id) return errorResponse('Cycle not found', 404)
    return envelope(
      {
        committeeAssessors: committeeAssessors.map(rosterRow),
        available: availableCommitteeAssessors().map((c) => ({ id: c.id, name: { ...c.name } })),
      },
      {
        cycleName: { ...committeeCycle.name },
        qualifiedCount: qualifiedAssessmentIds().length,
      },
    )
  }),

  http.post(
    '/api/v1/award/committee/:cycleId/roster/committee-assessors',
    async ({ request, params }) => {
      await delay(360)
      if (maybeFail()) return errorResponse('Could not add the assessor', 500)
      if (params.cycleId !== committeeCycle.id) return errorResponse('Cycle not found', 404)
      const body = await request.json()
      const candidate = availableCommitteeAssessors().find((c) => c.id === body.committeeAssessorId)
      if (!candidate)
        return errorResponse('Assessor is not available to add', 422, {
          committeeAssessorId: 'unavailable',
        })
      const committeeAssessor = addCommitteeAssessorToCommittee(candidate)
      return envelope(rosterRow(committeeAssessor))
    },
  ),

  http.delete(
    '/api/v1/award/committee/:cycleId/roster/committee-assessors/:committeeAssessorId',
    async ({ params }) => {
      await delay(400)
      if (maybeFail()) return errorResponse('Could not remove the assessor', 500)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      const affected = removeCommitteeAssessorFromCommittee(params.committeeAssessorId)
      const recomputed = affected.map((assessmentId) => {
        const average = ensureAverage(assessmentId)
        const value = refreshAverage(assessmentId)
        average.secretaryStatus = 'pending'
        return { assessmentId, average: value }
      })
      return envelope({
        committeeAssessorId: params.committeeAssessorId,
        removed: true,
        recomputed,
      })
    },
  ),

  http.post('/api/v1/award/committee/:cycleId/roster/reassign', async ({ request, params }) => {
    await delay(380)
    if (maybeFail()) return errorResponse('Could not reassign the assignment', 500)
    if (params.cycleId !== committeeCycle.id) return errorResponse('Cycle not found', 404)
    const body = await request.json()
    const result = reassignAssessment(
      body.fromCommitteeAssessorId,
      body.toCommitteeAssessorId,
      body.assessmentId,
    )
    if (!result.moved) {
      return errorResponse('The assignment could not be moved', 422, { assignment: 'not-movable' })
    }
    const average = ensureAverage(result.assessmentId)
    refreshAverage(result.assessmentId)
    average.secretaryStatus = 'pending'
    return envelope(result)
  }),

  http.post('/api/v1/award/committee/:cycleId/roster/redistribute', async ({ params }) => {
    await delay(460)
    if (maybeFail()) return errorResponse('Could not redistribute the assignments', 500)
    if (params.cycleId !== committeeCycle.id) return errorResponse('Cycle not found', 404)
    const result = redistributeAssignments()
    if (!result.balanced)
      return errorResponse('No active assessors to balance', 422, {
        committeeAssessors: 'none-active',
      })
    for (const assessmentId of qualifiedAssessmentIds()) {
      const average = ensureAverage(assessmentId)
      refreshAverage(assessmentId)
      if (average.secretaryStatus !== 'approved') average.secretaryStatus = 'pending'
    }
    return envelope(result)
  }),
]

export const secretaryHandlers = [
  http.post(
    '/api/v1/award/committee/:cycleId/averages/:assessmentId/compute',
    async ({ params }) => {
      await delay(400)
      if (maybeFail()) return errorResponse('Could not compute the average', 500)
      if (!allActiveCommitteeAssessorsCompleted(params.assessmentId)) {
        return errorResponse('All assessors must complete before computing the average', 422, {
          status: 'committee-assessors-incomplete',
        })
      }
      const average = ensureAverage(params.assessmentId)
      refreshAverage(params.assessmentId)
      average.secretaryStatus = 'pending'
      return envelope(averageView(average))
    },
  ),

  http.get('/api/v1/award/committee/:cycleId/secretary/pending', async ({ params }) => {
    await delay(320)
    if (maybeFail()) return errorResponse('Failed to load the pending approvals', 503)
    if (params.cycleId !== committeeCycle.id) return errorResponse('Cycle not found', 404)
    const rows = committeeAverages.filter((a) => a.secretaryStatus === 'pending').map(averageView)
    return envelope(rows, { cycleName: { ...committeeCycle.name } })
  }),

  http.post(
    '/api/v1/award/committee/:cycleId/secretary/:assessmentId/approve',
    async ({ params }) => {
      await delay(420)
      if (maybeFail()) return errorResponse('Could not approve the average', 500)
      const average = findAverage(params.assessmentId)
      if (!average) return errorResponse('Average not found', 404)
      const substitutes = committeeScores.filter(
        (s) => s.assessmentId === Number(params.assessmentId) && s.substituteFor,
      )
      for (const sub of substitutes) {
        removeCommitteeAssessorScores(sub.substituteFor)
        const old = findCommitteeAssessor(sub.substituteFor)
        if (old) old.status = 'excluded'
      }
      average.secretaryStatus = 'approved'
      average.approvedAt = new Date().toISOString()
      average.generalNote = null
      return envelope(averageView(average))
    },
  ),

  http.post(
    '/api/v1/award/committee/:cycleId/secretary/:assessmentId/return',
    async ({ request, params }) => {
      await delay(420)
      if (maybeFail()) return errorResponse('Could not return to the assessors committee', 500)
      const average = findAverage(params.assessmentId)
      if (!average) return errorResponse('Average not found', 404)
      const body = await request.json()
      const generalNote = body.generalNote ?? { ar: '', en: '' }
      const affected = committeeScores.filter((s) => s.assessmentId === Number(params.assessmentId))
      for (const rec of affected) {
        const committeeAssessor = findCommitteeAssessor(rec.committeeAssessorId)
        if (
          !committeeAssessor ||
          committeeAssessor.status === 'excluded' ||
          committeeAssessor.status === 'disabled'
        )
          continue
        rec.status = 'in-progress'
        rec.total = null
        rec.completedAt = null
        if (committeeAssessor.status === 'completed') committeeAssessor.status = 'in-progress'
      }
      average.secretaryStatus = 'returned'
      average.returnedAt = new Date().toISOString()
      average.generalNote =
        typeof generalNote === 'string' ? { ar: generalNote, en: generalNote } : { ...generalNote }
      average.average = null
      return envelope(averageView(average))
    },
  ),

  http.get('/api/v1/award/committee/:cycleId/secretary/entities', async ({ params }) => {
    await delay(320)
    if (maybeFail()) return errorResponse('Failed to load the entities', 503)
    if (params.cycleId !== committeeCycle.id) return errorResponse('Cycle not found', 404)
    const rows = filteringResults
      .filter((f) => f.filteringStatus === 'qualified')
      .map((f) => {
        const records = scoresForAssessment(f.assessmentId)
        const average = findAverage(f.assessmentId)
        return {
          assessmentId: f.assessmentId,
          entityName: { ...f.entityName },
          committeeAssessorCount: records.length,
          completedCount: records.filter((r) => r.status === 'completed').length,
          average: average?.average ?? computeAverageFor(f.assessmentId),
          secretaryStatus: average?.secretaryStatus ?? 'pending',
          allCompleted: allActiveCommitteeAssessorsCompleted(f.assessmentId),
        }
      })
    return envelope(rows, { cycleName: { ...committeeCycle.name } })
  }),

  http.get(
    '/api/v1/award/committee/:cycleId/secretary/:assessmentId/committee-assessors',
    async ({ params }) => {
      await delay(300)
      if (maybeFail()) return errorResponse('Failed to load the assessors', 503)
      const filtering = findFilteringResult(params.assessmentId)
      if (!filtering) return errorResponse('Entity not found', 404)
      const records = scoresForAssessment(params.assessmentId).map((rec) => {
        const committeeAssessor = findCommitteeAssessor(rec.committeeAssessorId)
        return {
          committeeAssessorId: rec.committeeAssessorId,
          committeeAssessorName: committeeAssessor ? { ...committeeAssessor.name } : null,
          status: rec.status,
          total: rec.total,
          substituteFor: rec.substituteFor ?? null,
          completedAt: rec.completedAt ?? null,
        }
      })
      const average = findAverage(params.assessmentId)
      return envelope(records, {
        entityName: { ...filtering.entityName },
        average: average?.average ?? computeAverageFor(params.assessmentId),
        secretaryStatus: average?.secretaryStatus ?? 'pending',
      })
    },
  ),

  http.get(
    '/api/v1/award/committee/:cycleId/secretary/:assessmentId/committee-assessors/:committeeAssessorId/detail',
    async ({ params }) => {
      await delay(320)
      if (maybeFail()) return errorResponse('Failed to load the detail', 503)
      const committeeAssessor = findCommitteeAssessor(params.committeeAssessorId)
      if (!committeeAssessor) return errorResponse('Assessor not found', 404)
      const record = findCommitteeAssessorScore(params.committeeAssessorId, params.assessmentId)
      const filtering = findFilteringResult(params.assessmentId)
      if (!record) return errorResponse('This assessor has no result for the entity', 404)
      return envelope({
        committeeAssessorName: { ...committeeAssessor.name },
        committeeAssessorStatus: record.status,
        entityName: filtering ? { ...filtering.entityName } : null,
        total: record.total,
        scaleKind: assessmentFixture.scale,
        tree: scoringTree(),
        scores: { subs: { ...record.scores.subs }, points: { ...record.scores.points } },
        notes: record.notes.map((n) => ({ ...n })),
        completedAt: record.completedAt ?? null,
      })
    },
  ),
]

function ensureRecord(committeeAssessorId, assessmentId) {
  const existing = findCommitteeAssessorScore(committeeAssessorId, assessmentId)
  if (existing) return existing
  return upsertCommitteeAssessorScore({
    cycleId: committeeCycle.id,
    committeeAssessorId,
    assessmentId: Number(assessmentId),
    status: 'not-started',
    total: null,
    scores: { subs: {}, points: {} },
    notes: [],
    completedAt: null,
  })
}

function ensureAverage(assessmentId) {
  const existing = findAverage(assessmentId)
  if (existing) return existing
  const filtering = findFilteringResult(assessmentId)
  const created = {
    cycleId: committeeCycle.id,
    assessmentId: Number(assessmentId),
    entityId: filtering?.entityId ?? null,
    entityName: filtering ? { ...filtering.entityName } : { ar: '', en: '' },
    average: null,
    committeeAssessorCount: committeeAssessors.filter(
      (j) => j.status !== 'excluded' && j.status !== 'disabled',
    ).length,
    completedCount: 0,
    secretaryStatus: 'pending',
    computedAt: null,
    approvedAt: null,
    returnedAt: null,
    generalNote: null,
  }
  committeeAverages.push(created)
  return created
}
