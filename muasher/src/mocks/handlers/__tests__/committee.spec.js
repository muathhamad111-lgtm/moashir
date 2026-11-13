import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { findAssessment } from '@/mocks/stores/assessments/assessmentsStore'
import { findCycle } from '@/mocks/stores/award/cycleStore'
import { writeAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { discardQueuedJobs } from '@/mocks/stores/mockQueue'
import { clearMockSession, seedMockSession } from '@/test-utils/mockApi'
import { PEOPLE as P, REASON, call, fullSheet, keys, resetWorld } from './committeeSupport'

const server = setupServer(...handlers)
const MEMBER_KEYS = [
  'appointed_at',
  'disabled_at',
  'disabled_reason',
  'id',
  'is_substitute',
  'progress',
  'removed_at',
  'replaced_by_id',
  'replaces_id',
  'status',
  'user',
]
const ROW_KEYS = [
  'assessment_id',
  'cycle',
  'due_at',
  'entity',
  'id',
  'is_overdue',
  'is_substitute',
  'membership_status',
  'percent',
  'returned_at',
  'started_at',
  'status',
  'submitted_at',
  'substitute_approved_at',
]
const EVALUATION_KEYS = [
  ...ROW_KEYS.filter((key) => !['cycle', 'entity'].includes(key)),
  'assignment',
  'assignment_id',
  'criteria',
  'cv',
  'evidence',
  'mode',
  'notes',
  'prior',
  'progress',
  'return_note',
  'scale',
  'scores',
]
  .filter(
    (key) =>
      ![
        'assessment_id',
        'due_at',
        'id',
        'is_overdue',
        'is_substitute',
        'membership_status',
        'returned_at',
        'started_at',
        'submitted_at',
        'substitute_approved_at',
      ].includes(key),
  )
  .sort()

async function rowOf(person, assessmentId) {
  seedMockSession(person)
  const { body } = await call('GET', '/committee/me/assignments?per_page=50')
  return body.data.find((row) => row.assessment_id === assessmentId)
}

async function scoreAndSubmit(person, assessmentId) {
  const row = await rowOf(person, assessmentId)
  const evaluation = (await call('GET', `/committee/me/assignments/${row.id}`)).body.data
  await call('PUT', `/committee/me/assignments/${row.id}/scores`, {
    scores: fullSheet(evaluation.criteria),
  })
  return call('POST', `/committee/me/assignments/${row.id}/submit`, {})
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  discardQueuedJobs()
  resetWorld(null)
})
afterAll(() => server.close())

describe('committee mock — roster', () => {
  it('lets managers appoint candidates with SoD checks and readers only read', async () => {
    resetWorld('qualified')
    seedMockSession(P.SECRETARY)
    const candidates = (await call('GET', '/award/cycles/31/committee/candidates')).body.data
    expect(candidates.map((person) => person.id).sort((a, b) => a - b)).toEqual([27, 29, 30, 31])
    expect(keys(candidates[0])).toEqual(['id', 'name'])
    expect(
      (await call('POST', '/award/cycles/31/committee/members', { user_id: 10 })).body.code,
    ).toBe('sod_conflict')
    expect(
      (await call('POST', '/award/cycles/31/committee/members', { user_id: 13 })).body.code,
    ).toBe('committee.candidate_invalid')
    expect((await call('POST', '/award/cycles/31/committee/members', {})).status).toBe(422)
    const appointed = await call('POST', '/award/cycles/31/committee/members', { user_id: 27 })
    expect(appointed.status).toBe(201)
    expect(keys(appointed.body.data)).toEqual(MEMBER_KEYS)
    expect(appointed.body.data).toMatchObject({
      status: 'active',
      user: { id: 27 },
      progress: { not_started: 0, in_progress: 0, completed: 0, voided: 0 },
    })
    expect(
      (await call('POST', '/award/cycles/31/committee/members', { user_id: 27 })).body.code,
    ).toBe('committee.member_exists')
    seedMockSession(P.CONTENT_OFFICER)
    expect((await call('GET', '/award/cycles/31/committee')).status).toBe(403)
    seedMockSession(P.SUPER_ADMIN)
    expect(
      (await call('POST', '/award/cycles/31/committee/members', { user_id: 29 })).body.code,
    ).toBe('reason_required')
  })

  it('starts the committee only with an active seat and distributes every qualified request', async () => {
    resetWorld('qualified')
    seedMockSession(P.SYSTEM_ADMIN)
    expect((await call('POST', '/award/cycles/31/start-committee', {})).body.code).toBe(
      'committee.none_active',
    )
    await call('POST', '/award/cycles/31/committee/members', { user_id: 27 })
    await call('POST', '/award/cycles/31/committee/members', { user_id: 29 })
    const started = await call('POST', '/award/cycles/31/start-committee', {})
    expect(started.status).toBe(200)
    expect(started.body.data.status).toBe('committee_assessment')
    expect(findAssessment(9204).status).toBe('under-committee-assessment')
    const roster = (await call('GET', '/award/cycles/31/committee')).body.data
    expect(roster.map((member) => member.progress.not_started)).toEqual([2, 2])
    expect((await call('POST', '/award/cycles/31/start-committee', {})).body.code).toBe(
      'state.transition_illegal',
    )
  })

  it('disables with a reason, substitutes with read-only prior work and refuses removal after scores', async () => {
    resetWorld('committee')
    seedMockSession(P.FOLLOWER)
    const roster = (await call('GET', '/award/cycles/31/committee')).body.data
    const omar = roster.find((member) => member.user.id === 30)
    expect(
      (await call('DELETE', `/award/cycles/31/committee/members/${omar.id}`, {})).body.code,
    ).toBe('committee_assessor.has_scores')
    expect(
      (await call('POST', `/award/cycles/31/committee/members/${omar.id}/disable`, {})).status,
    ).toBe(422)
    expect(
      (
        await call('POST', `/award/cycles/31/committee/members/${omar.id}/disable`, {
          reason: REASON,
        })
      ).body.data.status,
    ).toBe('disabled')
    const substitute = await call(
      'POST',
      `/award/cycles/31/committee/members/${omar.id}/substitute`,
      { substitute_user_id: 31 },
    )
    expect(substitute.status).toBe(201)
    expect(substitute.body.data).toMatchObject({ is_substitute: true, replaces_id: omar.id })
    const row = await rowOf(P.LAYLA, 9204)
    expect(row.is_substitute).toBe(true)
    const evaluation = (await call('GET', `/committee/me/assignments/${row.id}`)).body.data
    expect(evaluation.prior).toMatchObject({ view_only: true, status: 'completed' })
    seedMockSession(P.OMAR)
    const disabledRow = await rowOf(P.OMAR, 9204)
    expect(disabledRow.membership_status).toBe('disabled')
    const refused = await call('PUT', `/committee/me/assignments/${disabledRow.id}/notes`, {
      general_note: 'ملاحظة إضافية',
    })
    expect(refused.status).toBe(403)
    expect(refused.body.code).toBe('committee.membership_inactive')
  })

  it('excludes a disabled assessor and recomputes the affected averages', async () => {
    resetWorld('committee')
    seedMockSession(P.SECRETARY)
    const roster = (await call('GET', '/award/cycles/31/committee')).body.data
    const omar = roster.find((member) => member.user.id === 30)
    expect(
      (await call('POST', `/award/cycles/31/committee/members/${omar.id}/exclude`, {})).body.code,
    ).toBe('state.transition_illegal')
    await call('POST', `/award/cycles/31/committee/members/${omar.id}/disable`, { reason: REASON })
    const excluded = await call('POST', `/award/cycles/31/committee/members/${omar.id}/exclude`, {})
    expect(excluded.body.data.status).toBe('excluded')
    expect(excluded.body.meta.affected_request_ids.sort()).toEqual([9202, 9204, 9205])
    expect(excluded.body.meta.recomputed_request_ids.sort()).toEqual([9202, 9205])
  })
})

describe('committee mock — blind evaluation', () => {
  it('lists only the own rows with the entity per the visibility setting', async () => {
    resetWorld('committee')
    seedMockSession(P.DAVID)
    const { body } = await call('GET', '/committee/me/assignments')
    expect(body.data).toHaveLength(3)
    expect(keys(body.data[0])).toEqual(ROW_KEYS)
    expect(keys(body.data[0].entity)).toEqual(['name', 'region', 'visibility'])
    expect(body.meta.pagination).toMatchObject({ total: 3 })
    writeAssessmentSettings({ committeeEntityDataVisibility: 'code_only' })
    const coded = (await call('GET', '/committee/me/assignments')).body.data[0].entity
    expect(keys(coded)).toEqual(['code', 'region', 'visibility'])
    expect(coded.code).toMatch(/^C-[0-9A-F]{8}$/)
    expect((await call('GET', '/committee/me/assignments?filter[status]=finished')).status).toBe(
      422,
    )
    seedMockSession(P.SECRETARY)
    expect((await call('GET', '/committee/me/assignments')).status).toBe(403)
  })

  it('serves the evaluation without the initial result and hides other rows', async () => {
    resetWorld('committee')
    const mine = await rowOf(P.DAVID, 9204)
    const { body } = await call('GET', `/committee/me/assignments/${mine.id}`)
    expect(keys(body.data)).toEqual(EVALUATION_KEYS)
    expect(body.data).toMatchObject({
      mode: 'edit',
      status: 'in_progress',
      prior: null,
      return_note: null,
    })
    expect(JSON.stringify(body.data)).not.toContain('initial_percent')
    const file = body.data.evidence[0]
    expect((await call('POST', `/assessments/9204/evidence/${file.id}/download`)).status).toBe(200)
    seedMockSession(P.LAYLA)
    expect((await call('POST', `/assessments/9204/evidence/${file.id}/download`)).status).not.toBe(
      200,
    )
    const other = await rowOf(P.MANAL, 9204)
    seedMockSession(P.DAVID)
    expect((await call('GET', `/committee/me/assignments/${other.id}`)).status).toBe(404)
  })

  it('saves scores and notes, refuses an incomplete submit, locks after submit and averages automatically', async () => {
    resetWorld('committee')
    const mine = await rowOf(P.DAVID, 9204)
    const evaluation = (await call('GET', `/committee/me/assignments/${mine.id}`)).body.data
    expect((await call('POST', `/committee/me/assignments/${mine.id}/submit`, {})).body.code).toBe(
      'scores.incomplete',
    )
    const firstSub = evaluation.criteria.mains[0].subs[0].id
    const notes = await call('PUT', `/committee/me/assignments/${mine.id}/notes`, {
      notes: { [firstSub]: 'ملاحظة على المعيار الفرعي الأول.' },
      general_note: 'ملاحظة عامة على أداء الجهة.',
    })
    expect(keys(notes.body.data)).toEqual(['assignment_id', 'general', 'subs'])
    expect(
      (await call('PUT', `/committee/me/assignments/${mine.id}/notes`, { notes: { 999999: 'نص' } }))
        .body.code,
    ).toBe('assessment.sub_not_in_version')
    const scores = await call('PUT', `/committee/me/assignments/${mine.id}/scores`, {
      scores: fullSheet(evaluation.criteria, '9'),
    })
    expect(keys(scores.body.data)).toEqual([
      'assignment_id',
      'percent',
      'progress',
      'scale',
      'scores',
      'status',
    ])
    expect(scores.body.data.progress.scored).toBe(scores.body.data.progress.required)
    expect(
      (
        await call('PUT', `/committee/me/assignments/${mine.id}/scores`, {
          scores: { subs: { [firstSub]: '11' } },
        })
      ).status,
    ).toBe(422)
    const submitted = await call('POST', `/committee/me/assignments/${mine.id}/submit`, {})
    expect(keys(submitted.body.data)).toEqual(['id', 'percent', 'status', 'submitted_at'])
    expect(submitted.body.data.status).toBe('completed')
    expect(
      (
        await call('PUT', `/committee/me/assignments/${mine.id}/scores`, {
          scores: fullSheet(evaluation.criteria),
        })
      ).body.code,
    ).toBe('committee.evaluation_locked')
    expect(findAssessment(9204).status).toBe('under-committee-assessment')
    await scoreAndSubmit(P.MANAL, 9204)
    expect(findAssessment(9204).status).toBe('awaiting-secretary-approval')
    expect(findAssessment(9204).committeeAverageId).not.toBeNull()
    expect(findCycle(31).status).toBe('committee-assessment')
  })

  it('exposes the active seats as session committee appointments', async () => {
    resetWorld('committee')
    seedMockSession(P.DAVID)
    const { body } = await call('GET', '/auth/session')
    expect(body.data.committee_appointments).toHaveLength(1)
    expect(keys(body.data.committee_appointments[0])).toEqual([
      'award_cycle_id',
      'committee_end',
      'committee_start',
      'cycle_name',
      'cycle_status',
      'id',
      'is_substitute',
      'status',
    ])
    seedMockSession(P.INITIAL_ASSESSOR)
    expect((await call('GET', '/auth/session')).body.data.committee_appointments).toEqual([])
  })
})

describe('committee mock — discussions', () => {
  it('shows anonymous entries to members, records opinions and enforces the mode', async () => {
    resetWorld('committee')
    seedMockSession(P.DAVID)
    const { body } = await call('GET', '/committee/me/discussions')
    expect(body.data).toHaveLength(1)
    const discussion = body.data[0]
    expect(keys(discussion)).toEqual([
      'accepts_entries',
      'agreed_outcome',
      'assessment_id',
      'closed_at',
      'deadline_at',
      'entity',
      'entries',
      'id',
      'mode',
      'my_entry',
      'opened_at',
      'status',
    ])
    expect(keys(discussion.entries[0])).toEqual(['at', 'body', 'id', 'is_mine', 'kind'])
    expect(
      (
        await call('POST', `/committee/me/discussions/${discussion.id}/entries`, {
          kind: 'approve',
        })
      ).body.code,
    ).toBe('discussion.entry_kind_invalid')
    expect(
      (
        await call('POST', `/committee/me/discussions/${discussion.id}/entries`, {
          kind: 'opinion',
          body: 'قصير',
        })
      ).status,
    ).toBe(422)
    const entry = await call('POST', `/committee/me/discussions/${discussion.id}/entries`, {
      kind: 'opinion',
      body: 'أتفق مع المتوسط وأقترح التنويه بممارسات الحوكمة.',
    })
    expect(entry.status).toBe(201)
    expect(keys(entry.body.data)).toEqual(['at', 'body', 'discussion_id', 'id', 'kind'])
    const mine = (await call('GET', '/committee/me/discussions')).body.data[0].my_entry
    expect(mine.is_mine).toBe(true)
    seedMockSession(P.LAYLA)
    expect(
      (
        await call('POST', `/committee/me/discussions/${discussion.id}/entries`, {
          kind: 'opinion',
          body: 'رأي من خارج اللجنة لهذا الطلب.',
        })
      ).status,
    ).toBe(404)
  })
})

describe('committee mock — withdrawal', () => {
  it('voids the committee rows of a withdrawn request', async () => {
    resetWorld('committee')
    seedMockSession(P.FOLLOWER)
    const withdrawn = await call('POST', '/requests/9204/withdraw', { reason: REASON })
    expect(withdrawn.status).toBe(200)
    seedMockSession(P.DAVID)
    const rows = (await call('GET', '/committee/me/assignments')).body.data
    expect(rows.map((row) => row.assessment_id)).not.toContain(9204)
  })
})
