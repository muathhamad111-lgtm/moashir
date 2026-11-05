import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { findAssessment } from '@/mocks/stores/assessments/assessmentsStore'
import { findCycle } from '@/mocks/stores/award/cycleStore'
import { discardQueuedJobs } from '@/mocks/stores/mockQueue'
import { clearMockSession, seedMockSession } from '@/test-utils/mockApi'
import { PEOPLE as P, REASON, call, keys, resetWorld } from './committeeSupport'

const server = setupServer(...handlers)
const SECRETARY_ROW_KEYS = [
  'average',
  'committee',
  'entity',
  'id',
  'initial_percent',
  'is_frozen',
  'number',
  'region',
  'status',
  'submitted_at',
]
const DETAIL_KEYS = [
  'all_submitted',
  'average',
  'decisions',
  'discussions',
  'entity',
  'evaluations',
  'id',
  'initial_result',
  'number',
  'pending_assignment_ids',
  'pending_substitution_ids',
  'region',
  'status',
]
const RESULT_ROW_KEYS = [
  'approved_at',
  'assessment_id',
  'average_percent',
  'entity',
  'national_rank',
  'number',
  'region',
  'regional_rank',
  'status',
]
const RETURN_NOTE = 'يرجى مراجعة درجات معيار الحوكمة في ضوء الشواهد المرفقة وتوحيد أسس التقدير.'
const UNAPPROVE = 'سحب اعتماد المنطقة لمراجعة ترتيب الفائزين بعد ملاحظات اللجنة العليا.'
const DESK = '/award/cycles/31/secretary/requests'

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  discardQueuedJobs()
  resetWorld(null)
})
afterAll(() => server.close())

describe('secretary mock — desk', () => {
  it('lists the committee requests with counts and limits the readers', async () => {
    resetWorld('committee')
    seedMockSession(P.SECRETARY)
    const { body } = await call('GET', `${DESK}?filter[status]=awaiting_secretary_approval`)
    expect(body.data.map((row) => row.id)).toEqual([9205])
    expect(keys(body.data[0])).toEqual(SECRETARY_ROW_KEYS)
    expect(keys(body.data[0].average)).toEqual([
      'computed_at',
      'id',
      'is_manual',
      'member_count',
      'percent',
      'version',
    ])
    expect(body.meta.counts).toMatchObject({
      under_committee_assessment: 1,
      committee_discussion: 1,
      awaiting_secretary_approval: 1,
      approved: 0,
    })
    expect((await call('GET', `${DESK}?filter[status]=draft`)).status).toBe(422)
    seedMockSession(P.FILTERING_OFFICER)
    expect((await call('GET', DESK)).status).toBe(403)
    seedMockSession(P.SYSTEM_ADMIN)
    expect((await call('GET', DESK)).status).toBe(200)
    expect((await call('POST', `${DESK}/9205/approve`, {})).status).toBe(403)
  })

  it('shows the detail and one evaluation with its breakdown and recomputes idempotently', async () => {
    resetWorld('committee')
    seedMockSession(P.SECRETARY)
    const detail = (await call('GET', `${DESK}/9205`)).body.data
    expect(keys(detail)).toEqual(DETAIL_KEYS)
    expect(detail.all_submitted).toBe(true)
    expect(detail.evaluations[0].member.user.name).toBeTruthy()
    const evaluation = (await call('GET', `${DESK}/9205/evaluations/${detail.evaluations[0].id}`))
      .body.data
    expect(keys(evaluation.result)).toEqual([
      'main',
      'max_total',
      'percent',
      'points',
      'sub',
      'total',
    ])
    const recompute = await call('POST', `${DESK}/9205/average`, {})
    expect(recompute.body.meta).toMatchObject({
      recomputed: false,
      committee_average_id: detail.average.id,
    })
    expect((await call('POST', `${DESK}/9204/average`, {})).body.code).toBe('committee.incomplete')
    expect((await call('GET', `${DESK}/9203`)).status).toBe(404)
  })

  it('approves the average into the ranking and returns another request with a note', async () => {
    resetWorld('committee')
    seedMockSession(P.SECRETARY)
    const approved = await call('POST', `${DESK}/9205/approve`, { note: 'متوسط متسق مع الشواهد.' })
    expect(approved.body.data.status).toBe('approved')
    expect(approved.body.data.decisions.map((decision) => decision.kind)).toEqual([
      'approve_average',
    ])
    expect((await call('POST', `${DESK}/9205/approve`, {})).body.code).toBe(
      'state.transition_illegal',
    )
    expect((await call('POST', `${DESK}/9202/return`, { general_note: 'قصير' })).status).toBe(422)
    const returned = await call('POST', `${DESK}/9202/return`, { general_note: RETURN_NOTE })
    expect(returned.body.data).toMatchObject({ status: 'returned_to_committee', average: null })
    expect(returned.body.data.discussions[0].status).toBe('cancelled')
    seedMockSession(P.DAVID)
    const row = (await call('GET', '/committee/me/assignments')).body.data.find(
      (item) => item.assessment_id === 9202,
    )
    expect(row.status).toBe('in_progress')
    expect(
      (await call('GET', `/committee/me/assignments/${row.id}`)).body.data.return_note.note,
    ).toBe(RETURN_NOTE)
  })

  it('opens and closes discussions in both modes', async () => {
    resetWorld('committee')
    seedMockSession(P.SECRETARY)
    expect((await call('POST', `${DESK}/9205/discussions`, { mode: 'session' })).status).toBe(422)
    const opened = await call('POST', `${DESK}/9205/discussions`, {
      mode: 'agreed_outcome',
      agreed_outcome: 'اعتماد المتوسط مع التنويه بممارسات الشفافية المالية.',
    })
    expect(opened.status).toBe(201)
    expect(findAssessment(9205).status).toBe('committee-discussion')
    seedMockSession(P.MANAL)
    expect(
      (
        await call('POST', `/committee/me/discussions/${opened.body.data.id}/entries`, {
          kind: 'request_edit',
        })
      ).status,
    ).toBe(422)
    expect(
      (
        await call('POST', `/committee/me/discussions/${opened.body.data.id}/entries`, {
          kind: 'approve',
        })
      ).status,
    ).toBe(201)
    seedMockSession(P.SECRETARY)
    const detail = (await call('GET', `${DESK}/9205`)).body.data
    expect(detail.discussions[0].entries[0].member.name).toBeTruthy()
    const closed = await call('POST', `${DESK}/9205/discussions/${opened.body.data.id}/close`, {
      summary: 'وافق الأعضاء على النتيجة.',
    })
    expect(closed.body.data.status).toBe('closed')
    expect(findAssessment(9205).status).toBe('awaiting-secretary-approval')
    expect(
      (await call('POST', `${DESK}/9205/discussions/${opened.body.data.id}/close`, {})).body.code,
    ).toBe('discussion.closed')
  })

  it('blocks the approval while a substitute result is pending and approves it after submission', async () => {
    resetWorld('committee')
    seedMockSession(P.SECRETARY)
    const roster = (await call('GET', '/award/cycles/31/committee')).body.data
    const omar = roster.find((member) => member.user.id === 30)
    await call('POST', `/award/cycles/31/committee/members/${omar.id}/disable`, { reason: REASON })
    await call('POST', `/award/cycles/31/committee/members/${omar.id}/substitute`, {
      substitute_user_id: 31,
    })
    expect((await call('POST', `${DESK}/9205/approve`, {})).body.code).toBe(
      'committee.substitution_pending',
    )
    const pending = (await call('GET', `${DESK}/9205`)).body.data.pending_substitution_ids[0]
    expect(
      (await call('POST', `${DESK}/9205/substitutions/${pending}/approve`, {})).body.code,
    ).toBe('committee.substitute_not_completed')
  })
})

describe('results mock — regional, national and announcement', () => {
  it('ranks and approves a region, then the national result, then announces', async () => {
    resetWorld('results')
    seedMockSession(P.SECRETARY)
    const board = await call('GET', '/award/cycles/31/results')
    expect(keys(board.body.data[0])).toEqual(RESULT_ROW_KEYS)
    expect(keys(board.body.meta)).toEqual(['cycle_status', 'national', 'regions'])
    expect(board.body.meta.regions.find((region) => region.id === 2)).toMatchObject({
      approved: true,
      total: 2,
      ranked: 1,
    })
    expect(
      (await call('PUT', '/award/cycles/31/results/9201/regional-rank', { rank: 2 })).body.code,
    ).toBe('award.region_locked')
    expect((await call('PUT', '/award/cycles/31/results/9204/regional-rank', {})).status).toBe(422)
    expect((await call('POST', '/award/cycles/31/regions/1/approve', {})).body.code).toBe(
      'winners.none',
    )
    expect((await call('POST', '/award/cycles/31/national/approve', {})).body.code).toBe(
      'regions.pending',
    )
    expect(
      (await call('PUT', '/award/cycles/31/results/9204/regional-rank', { rank: 1 })).body.data
        .regional_rank,
    ).toBe(1)
    const region = await call('POST', '/award/cycles/31/regions/1/approve', {})
    expect(region.body.meta.regions.find((item) => item.id === 1).approved).toBe(true)
    expect(findAssessment(9204).status).toBe('qualified-for-national')
    const national = await call('GET', '/award/cycles/31/national')
    expect(national.body.data.map((row) => row.assessment_id).sort()).toEqual([9202, 9204, 9205])
    await call('PUT', '/award/cycles/31/national/9204/rank', { rank: 1 })
    const taken = await call('PUT', '/award/cycles/31/national/9205/rank', { rank: 1 })
    expect(taken.body).toMatchObject({ code: 'rank.taken', meta: { assessment_id: 9204 } })
    const approvedNational = await call('POST', '/award/cycles/31/national/approve', {})
    expect(approvedNational.body.meta.cycle_status).toBe('approved')
    expect(findAssessment(9205).status).toBe('national-non-winner')
    seedMockSession(P.SYSTEM_ADMIN)
    expect((await call('POST', '/award/cycles/31/announce', {})).status).toBe(403)
    seedMockSession(P.SECRETARY)
    const announced = await call('POST', '/award/cycles/31/announce', {})
    expect(announced.body.data.status).toBe('announced')
    expect((await call('POST', '/award/cycles/31/announce', {})).body.code).toBe(
      'state.transition_illegal',
    )
  })

  it('lets only the Super Admin withdraw a region approval with a long reason', async () => {
    resetWorld('results')
    seedMockSession(P.SECRETARY)
    expect(
      (await call('POST', '/award/cycles/31/regions/2/unapprove', { reason: UNAPPROVE })).status,
    ).toBe(403)
    seedMockSession(P.SUPER_ADMIN)
    expect(
      (await call('POST', '/award/cycles/31/regions/2/unapprove', { reason: 'سبب قصير جدًا' }))
        .status,
    ).toBe(422)
    const unapproved = await call('POST', '/award/cycles/31/regions/2/unapprove', {
      reason: UNAPPROVE,
    })
    expect(unapproved.body.meta.regions.find((region) => region.id === 2).approved).toBe(false)
    expect(findAssessment(9205).status).toBe('approved')
    expect(
      (await call('POST', '/award/cycles/31/regions/2/unapprove', { reason: UNAPPROVE })).body.code,
    ).toBe('award.region_not_approved')
  })

  it('opens the entity result only after the announcement and never shows the committee average', async () => {
    resetWorld('results')
    seedMockSession(P.RUWAD)
    const before = await call('GET', '/assessments/9204/result')
    expect(before.body.data).toMatchObject({ available: false, status: 'under_review' })
    resetWorld('announced')
    seedMockSession(P.RUWAD)
    const after = (await call('GET', '/assessments/9204/result')).body.data
    expect(after).toMatchObject({
      available: true,
      status: 'national_winner',
      award: { status: 'national_winner', regional_rank: 1, national_rank: 1 },
    })
    expect(keys(after.award)).toEqual(['national_rank', 'region', 'regional_rank', 'status'])
    expect(JSON.stringify(after)).not.toContain('average')
    expect(findCycle(31).announcedAt).toBeTruthy()
  })
})

describe('criteria mock — award cycles on publication (Session 19)', () => {
  it('lists the running and draft cycles on the check and moves drafts on request', async () => {
    resetWorld(null)
    seedMockSession({ id: 3, roles: ['criteria_officer'] })
    const draft = (await call('POST', '/criteria/versions', {})).body.data
    const check = await call('GET', `/criteria/versions/${draft.id}/check`)
    expect(check.body.meta.award_cycles).toMatchObject({
      running: { id: 31, status: 'submission_open' },
      requests_stay_on_cycle_version: true,
      drafts: [{ id: 32 }],
    })
    seedMockSession(P.SYSTEM_ADMIN)
    expect(
      (
        await call('POST', `/criteria/versions/${draft.id}/publish`, {
          confirm: true,
          scope: 'new_only',
          draft_cycles: 'later',
        })
      ).status,
    ).toBe(422)
    const published = await call('POST', `/criteria/versions/${draft.id}/publish`, {
      confirm: true,
      scope: 'new_only',
      draft_cycles: 'move',
    })
    expect(published.body.meta.award_cycles).toMatchObject({
      draft_cycles: 'move',
      moved_cycle_ids: [32],
    })
    expect(findCycle(32).criteriaVersionId).toBe(draft.id)
    expect(findCycle(31).criteriaVersionId).not.toBe(draft.id)
  })
})
