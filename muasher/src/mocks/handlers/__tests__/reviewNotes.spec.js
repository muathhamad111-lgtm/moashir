import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

// Internal review notes (operator 2026-11-22): staff only, one note per author and unit, and the
// entity never receives them.
const server = setupServer(...handlers)

const ASSIGNEE = { id: 10, roles: ['assessor'] }
const OTHER_ASSESSOR = { id: 11, roles: ['assessor'] }
const FOLLOWER = { id: 8, roles: ['request_follower', 'filtering_officer'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const ENTITY = { id: 6002, roles: ['entity'], entityId: 6002 }
const MARKER = 'INTERNAL-MARKER-7731'
const REQUEST = 9108

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null, text }
}

async function firstUnits() {
  const { body } = await call('GET', `/assessments/${REQUEST}/review`)
  const main = body.data.criteria.mains[0]
  const sub = main.subs.find((candidate) => candidate.points.length)
  return { main, sub, point: sub.points[0] }
}

const subNote = ({ main, sub }, text) => ({
  level: 'sub',
  main_criterion_id: main.id,
  sub_criterion_id: sub.id,
  guiding_point_id: null,
  body: text,
})

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCriteriaStore()
  resetEntitiesMock()
  resetAssessmentsStore()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('internal review notes mock', () => {
  it('lets the assignee and the follower each keep their own note on the same unit', async () => {
    seedMockSession(ASSIGNEE)
    const units = await firstUnits()
    const saved = await call('PUT', `/assessments/${REQUEST}/review-notes`, {
      notes: [
        subNote(units, 'Assessor note'),
        { ...subNote(units, MARKER), level: 'point', guiding_point_id: units.point.id },
      ],
    })
    expect(saved.status).toBe(200)
    expect(saved.body.data.review_notes.map((note) => note.author.role)).toEqual([
      'assessor',
      'assessor',
    ])

    seedMockSession(FOLLOWER)
    await call('PUT', `/assessments/${REQUEST}/review-notes`, {
      notes: [subNote(units, 'Follower note')],
    })
    const { body } = await call('GET', `/assessments/${REQUEST}/review`)
    const onSub = body.data.review_notes.filter((note) => note.level === 'sub')
    expect(onSub.map((note) => [note.body, note.author.role, note.is_mine])).toEqual([
      ['Assessor note', 'assessor', false],
      ['Follower note', 'follower', true],
    ])
    expect(body.data.can_write_review_notes).toBe(true)
  })

  it('clears a note when its body is emptied', async () => {
    seedMockSession(ASSIGNEE)
    const units = await firstUnits()
    await call('PUT', `/assessments/${REQUEST}/review-notes`, { notes: [subNote(units, 'Draft')] })
    const cleared = await call('PUT', `/assessments/${REQUEST}/review-notes`, {
      notes: [subNote(units, '')],
    })
    expect(cleared.body.data.review_notes).toEqual([])
  })

  it('refuses another assessor and the entity, and never shows the notes to the entity', async () => {
    seedMockSession(ASSIGNEE)
    const units = await firstUnits()
    await call('PUT', `/assessments/${REQUEST}/review-notes`, { notes: [subNote(units, MARKER)] })

    seedMockSession(OTHER_ASSESSOR)
    const refused = await call('PUT', `/assessments/${REQUEST}/review-notes`, {
      notes: [subNote(units, 'x')],
    })
    expect(refused.status).toBe(403)

    seedMockSession(ENTITY)
    expect(
      (await call('PUT', `/assessments/${REQUEST}/review-notes`, { notes: [subNote(units, 'x')] }))
        .status,
    ).toBe(403)
    for (const path of [
      `/assessments/${REQUEST}`,
      '/assessments/accredited/current',
      '/assessments',
    ]) {
      expect((await call('GET', path)).text).not.toContain(MARKER)
    }
  })

  it('asks the Super Admin for a reason and checks the length', async () => {
    seedMockSession(ASSIGNEE)
    const units = await firstUnits()
    const long = await call('PUT', `/assessments/${REQUEST}/review-notes`, {
      notes: [subNote(units, 'x'.repeat(501))],
    })
    expect(long.status).toBe(422)

    seedMockSession(SUPER_ADMIN)
    const noReason = await call('PUT', `/assessments/${REQUEST}/review-notes`, {
      notes: [subNote(units, 'Super Admin note')],
    })
    expect(noReason.status).toBe(422)
    const withReason = await call('PUT', `/assessments/${REQUEST}/review-notes`, {
      notes: [subNote(units, 'Super Admin note')],
      reason: 'تصحيح موثق بعد مراجعة اللجنة الفنية.',
    })
    expect(withReason.body.data.review_notes[0].author.role).toBe('super_admin')
  })
})
