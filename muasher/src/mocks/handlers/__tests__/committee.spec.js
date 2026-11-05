import { describe, expect, it, beforeAll, afterAll } from 'vitest'
import { setupServer } from 'msw/node'
import { committeeHandlers, secretaryHandlers } from '@/mocks/handlers/committee'
import {
  COMMITTEE_CYCLE_ID,
  QUALIFIED_ASSESSMENT_A,
  QUALIFIED_ASSESSMENT_B,
  COMMITTEE_ASSESSOR_COMPLETED_A,
  COMMITTEE_ASSESSOR_COMPLETED_B,
  COMMITTEE_ASSESSOR_IN_PROGRESS,
  COMMITTEE_ASSESSOR_NOT_STARTED,
} from '@/mocks/fixtures/committee'
import { MOCK_API_BASE, mockFetch } from '@/test-utils/mockApi'

const server = setupServer(...committeeHandlers, ...secretaryHandlers)
const BASE = MOCK_API_BASE
const C = `/award/committee/${COMMITTEE_CYCLE_ID}`

async function jsonGet(path) {
  const res = await mockFetch(`${BASE}${path}`, { method: 'GET' })
  return { ok: res.ok, status: res.status, body: await res.json() }
}
async function jsonSend(method, path, body) {
  const init = { method, headers: { 'content-type': 'application/json' } }
  if (method !== 'DELETE') init.body = JSON.stringify(body ?? {})
  const res = await mockFetch(`${BASE}${path}`, init)
  return { ok: res.ok, status: res.status, body: await res.json() }
}
async function retryGet(path, tries = 20) {
  for (let i = 0; i < tries; i += 1) {
    const r = await jsonGet(path)
    if (r.ok) return r
  }
  throw new Error(`get failed: ${path}`)
}
async function retrySend(method, path, body, tries = 20) {
  for (let i = 0; i < tries; i += 1) {
    const r = await jsonSend(method, path, body)
    if (r.status !== 500 && r.status !== 503) return r
  }
  throw new Error(`send failed: ${path}`)
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterAll(() => server.close())

describe('award committee pipeline (mock)', () => {
  it('lists committee assessors with the §8.3 statuses (staff-facing)', async () => {
    const res = await retryGet(`${C}/committee-assessors`)
    const statuses = res.body.data.map((j) => j.status)
    expect(statuses).toContain('completed')
    expect(statuses).toContain('in-progress')
    expect(statuses).toContain('not-started')
    expect(res.body.data[0].name).toHaveProperty('ar')
    expect(res.body.data[0].name).toHaveProperty('en')
  })

  it('blocks computing the average until ALL committee assessors complete, then computes it', async () => {
    const blocked = await retrySend('POST', `${C}/averages/${QUALIFIED_ASSESSMENT_B}/compute`)
    expect(blocked.status).toBe(422)
    expect(blocked.body.errors.status).toBe('committee-assessors-incomplete')

    await retrySend(
      'PUT',
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_NOT_STARTED}/score/${QUALIFIED_ASSESSMENT_B}/sub`,
      {
        subCriterionId: 101,
        ratio: 0.8,
      },
    )
    await retrySend(
      'POST',
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_NOT_STARTED}/score/${QUALIFIED_ASSESSMENT_B}/complete`,
    )
    const still = await retrySend('POST', `${C}/averages/${QUALIFIED_ASSESSMENT_B}/compute`)
    expect(still.status).toBe(422)
    await retrySend(
      'POST',
      `${C}/committee-assessors/jur-9403/score/${QUALIFIED_ASSESSMENT_B}/complete`,
    )
    const ok = await retrySend('POST', `${C}/averages/${QUALIFIED_ASSESSMENT_B}/compute`)
    expect(ok.ok).toBe(true)
    expect(ok.body.data.average).toBeGreaterThan(0)
    expect(ok.body.data.secretaryStatus).toBe('pending')
  })

  it('lets the secretary approve a pending average and drops it from the pending list', async () => {
    const before = await retryGet(`${C}/secretary/pending`)
    const target = before.body.data.find((a) => a.assessmentId === QUALIFIED_ASSESSMENT_A)
    expect(target).toBeTruthy()
    expect(target.secretaryStatus).toBe('pending')

    const approved = await retrySend('POST', `${C}/secretary/${QUALIFIED_ASSESSMENT_A}/approve`)
    expect(approved.ok).toBe(true)
    expect(approved.body.data.secretaryStatus).toBe('approved')

    const after = await retryGet(`${C}/secretary/pending`)
    expect(after.body.data.find((a) => a.assessmentId === QUALIFIED_ASSESSMENT_A)).toBeUndefined()
  })

  it('returns an average to ALL committee assessors with a general note, reverting their completed status', async () => {
    const returned = await retrySend('POST', `${C}/secretary/${QUALIFIED_ASSESSMENT_B}/return`, {
      generalNote: {
        ar: 'يرجى إعادة النظر في المعيار الأول.',
        en: 'Please revisit the first criterion.',
      },
    })
    expect(returned.ok).toBe(true)
    expect(returned.body.data.secretaryStatus).toBe('returned')
    expect(returned.body.data.generalNote.en).toContain('first criterion')

    const scoreView = await retryGet(
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_NOT_STARTED}/score/${QUALIFIED_ASSESSMENT_B}`,
    )
    expect(scoreView.body.data.record.status).toBe('in-progress')
  })
})

describe('committee assessor blindness (Note 12)', () => {
  it("a committee assessor score endpoint returns ONLY that committee assessor's own record — no other committeeAssessor's scores or notes", async () => {
    const aView = await retryGet(
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_COMPLETED_A}/score/${QUALIFIED_ASSESSMENT_A}`,
    )
    const bView = await retryGet(
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_COMPLETED_B}/score/${QUALIFIED_ASSESSMENT_A}`,
    )

    expect(aView.body.data.record).toBeTruthy()
    expect(bView.body.data.record).toBeTruthy()
    expect(aView.body.data).not.toHaveProperty('committeeAssessors')
    expect(aView.body.data).not.toHaveProperty('otherScores')

    const marker = `blind-marker-${Date.now()}`
    await retrySend(
      'PUT',
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_COMPLETED_A}/score/${QUALIFIED_ASSESSMENT_A}/notes`,
      {
        subCriterionId: 101,
        text: marker,
      },
    )
    const aAfter = await retryGet(
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_COMPLETED_A}/score/${QUALIFIED_ASSESSMENT_A}`,
    )
    const bAfter = await retryGet(
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_COMPLETED_B}/score/${QUALIFIED_ASSESSMENT_A}`,
    )
    const aNotes = JSON.stringify(aAfter.body.data.record.notes)
    const bBlob = JSON.stringify(bAfter.body.data)
    expect(aNotes).toContain(marker)
    expect(bBlob).not.toContain(marker)

    expect(bBlob).not.toContain(COMMITTEE_ASSESSOR_COMPLETED_A)
  })
})

describe('committee management (Note 11)', () => {
  it('lists the manageable roster with assignment load + the pool of assessors available to add', async () => {
    const res = await retryGet(`${C}/roster`)
    const { committeeAssessors, available } = res.body.data
    expect(Array.isArray(committeeAssessors)).toBe(true)
    expect(committeeAssessors[0]).toHaveProperty('assignmentCount')
    expect(committeeAssessors[0]).toHaveProperty('assignments')
    expect(res.body.meta.qualifiedCount).toBeGreaterThan(0)
    expect(Array.isArray(available)).toBe(true)
    expect(available.every((c) => !committeeAssessors.some((j) => j.id === c.id))).toBe(true)
  })

  it('adds a committee assessor from the pool with the full qualified-entity load, then removes it', async () => {
    const before = await retryGet(`${C}/roster`)
    const candidate = before.body.data.available[0]
    expect(candidate).toBeTruthy()

    const added = await retrySend('POST', `${C}/roster/committee-assessors`, {
      committeeAssessorId: candidate.id,
    })
    expect(added.ok).toBe(true)
    expect(added.body.data.assignmentCount).toBe(before.body.meta.qualifiedCount)

    const afterAdd = await retryGet(`${C}/roster`)
    expect(afterAdd.body.data.committeeAssessors.some((j) => j.id === candidate.id)).toBe(true)
    expect(afterAdd.body.data.available.some((c) => c.id === candidate.id)).toBe(false)

    const removed = await retrySend('DELETE', `${C}/roster/committee-assessors/${candidate.id}`)
    expect(removed.ok).toBe(true)
    expect(removed.body.data.removed).toBe(true)
    const afterRemove = await retryGet(`${C}/roster`)
    expect(afterRemove.body.data.committeeAssessors.some((j) => j.id === candidate.id)).toBe(false)
  })

  it('reassigns ONE entity from one committee assessor to another (target starts a fresh blind slate)', async () => {
    const res = await retrySend('POST', `${C}/roster/reassign`, {
      fromCommitteeAssessorId: COMMITTEE_ASSESSOR_IN_PROGRESS,
      toCommitteeAssessorId: COMMITTEE_ASSESSOR_NOT_STARTED,
      assessmentId: QUALIFIED_ASSESSMENT_B,
    })
    expect(res.ok).toBe(true)
    expect(res.body.data.moved).toBe(true)

    const source = await retryGet(
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_IN_PROGRESS}/assignments`,
    )
    expect(source.body.data.some((a) => a.assessmentId === QUALIFIED_ASSESSMENT_B)).toBe(false)
    const target = await retryGet(
      `${C}/committee-assessors/${COMMITTEE_ASSESSOR_NOT_STARTED}/score/${QUALIFIED_ASSESSMENT_B}`,
    )
    expect(target.body.data.record.status).toBe('not-started')
    expect(Object.keys(target.body.data.record.scores.subs)).toHaveLength(0)
  })

  it('redistributes (balances) the qualified assessments across the active committee assessors', async () => {
    const res = await retrySend('POST', `${C}/roster/redistribute`)
    expect(res.ok).toBe(true)
    expect(res.body.data.balanced).toBe(true)
    const roster = await retryGet(`${C}/roster`)
    const active = roster.body.data.committeeAssessors.filter(
      (j) => j.status !== 'excluded' && j.status !== 'disabled',
    )
    for (const committeeAssessor of active) {
      expect(committeeAssessor.assignmentCount).toBe(res.body.data.assignmentsPerCommitteeAssessor)
    }
  })
})

describe('secretary review-all (Note 13)', () => {
  it('lists every qualified entity with its committee assessors aggregate', async () => {
    const res = await retryGet(`${C}/secretary/entities`)
    expect(res.body.data.length).toBeGreaterThan(0)
    const row = res.body.data[0]
    expect(row).toHaveProperty('entityName')
    expect(row).toHaveProperty('committeeAssessorCount')
    expect(row).toHaveProperty('completedCount')
    expect(row).toHaveProperty('secretaryStatus')
  })

  it('shows ALL committee assessors who scored one entity (aggregate for the secretary)', async () => {
    const res = await retryGet(`${C}/secretary/${QUALIFIED_ASSESSMENT_A}/committee-assessors`)
    expect(res.body.data.length).toBeGreaterThan(1)
    expect(res.body.data[0]).toHaveProperty('committeeAssessorName')
    expect(res.body.data[0]).toHaveProperty('status')
    expect(res.body.data[0]).toHaveProperty('total')
    expect(res.body.meta).toHaveProperty('entityName')
  })

  it("returns ONE committee assessor's full read-only detail (sub/point scores + per-sub notes)", async () => {
    const res = await retryGet(
      `${C}/secretary/${QUALIFIED_ASSESSMENT_A}/committee-assessors/${COMMITTEE_ASSESSOR_COMPLETED_A}/detail`,
    )
    expect(res.ok).toBe(true)
    expect(res.body.data).toHaveProperty('tree')
    expect(res.body.data).toHaveProperty('scores')
    expect(res.body.data).toHaveProperty('notes')
    expect(res.body.data.committeeAssessorName).toHaveProperty('ar')
    expect(Array.isArray(res.body.data.tree)).toBe(true)
    expect(res.body.data.tree[0]).toHaveProperty('subs')
  })
})
