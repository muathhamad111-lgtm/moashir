import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { findEntity } from '@/mocks/stores/entitiesStore'
import { findAssessment, resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { resetAccreditedOpening } from '@/mocks/stores/accredited/accreditedOpening'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { notificationsStore } from '@/mocks/stores/notificationsStore'
import { discardQueuedJobs, runQueuedJobs } from '@/mocks/stores/mockQueue'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)

const FOLLOWER = { id: 8, roles: ['request_follower', 'filtering_officer'] }
const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const SULTAN = { id: 10, roles: ['assessor'] }
const HIND = { id: 11, roles: ['assessor'] }
const FOLLOWING_ASSESSOR = { id: 10, roles: ['request_follower', 'assessor'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const NAMAA = { id: 6001, roles: ['entity'], entityId: 6001 }
const REASON = 'تصحيح موثق بعد مراجعة اللجنة الفنية للشواهد.'

const ROW_KEYS = [
  'assessor',
  'assigned_at',
  'award_cycle',
  'completed_at',
  'completion_deadline',
  'due_at',
  'entity',
  'id',
  'is_frozen',
  'is_overdue',
  'number',
  'path',
  'path_label',
  'region',
  'status',
  'submitted_at',
]
const REVIEW_KEYS = [
  'assessment',
  'completed_at',
  'completion_request',
  'context',
  'criteria',
  'cv',
  'due_at',
  'entity',
  'evidence',
  'feedback',
  'is_overdue',
  'mode',
  'progress',
  'scale',
  'scores',
  'submitted_at',
]

async function call(method, path, body, locale = 'en') {
  const init = { method, headers: { 'accept-language': locale } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

const keys = (value) => Object.keys(value).sort()
const inDays = (days) => new Date(Date.now() + days * 86400000).toISOString()

function allUnits(criteria, value = '8') {
  const sheet = { subs: {}, points: {} }
  criteria.mains.forEach((main) =>
    main.subs.forEach((sub) => {
      if (sub.points.length) sub.points.forEach((point) => (sheet.points[point.id] = value))
      else sheet.subs[sub.id] = value
    }),
  )
  return sheet
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCriteriaStore()
  resetEntitiesMock()
  resetFinanceState()
  resetAssessmentSettings()
  resetAccreditedOpening()
  resetAssessmentsStore()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  discardQueuedJobs()
})
afterAll(() => server.close())

describe('accredited mock — requests queue and assignment', () => {
  it('lists submitted requests with stats keyed by canonical status and refuses bad filters', async () => {
    seedMockSession(FOLLOWER)
    const { body } = await call('GET', '/requests?filter[path]=accredited')
    expect(keys(body.data[0])).toEqual(ROW_KEYS)
    expect(body.data.map((row) => row.id)).not.toContain(9101)
    expect(body.meta.stats).toEqual({
      total: 7,
      by_status: {
        submitted: 1,
        assigned: 1,
        under_review: 1,
        needs_completion: 1,
        completed: 2,
        refunded: 1,
        declined_incomplete: 0,
      },
      overdue: 2,
    })
    expect(
      (await call('GET', '/requests?filter[status]=declined_incomplete&filter[path]=accredited'))
        .body.data,
    ).toEqual([])
    expect(
      (await call('GET', '/requests?filter[overdue]=true&filter[path]=accredited')).body.data
        .map((row) => row.id)
        .sort(),
    ).toEqual([9105, 9107])
    expect(
      (await call('GET', '/requests?filter[assessor_id]=10&filter[path]=accredited')).body.data
        .map((row) => row.id)
        .sort(),
    ).toEqual([9107, 9108])
    expect(
      (await call('GET', '/requests?search=ACC-2026-000006&filter[path]=accredited')).body.data.map(
        (row) => row.id,
      ),
    ).toEqual([9106])
    expect(
      (await call('GET', '/requests?sort=due_at&filter[path]=accredited')).body.data[0].id,
    ).toBe(9102)
    expect((await call('GET', '/requests?sort=number')).status).toBe(422)
    expect((await call('GET', '/requests?filter[status]=draft')).status).toBe(422)
    seedMockSession(NAMAA)
    expect((await call('GET', '/requests')).status).toBe(403)
  })

  it('keeps frozen requests out of the queue', async () => {
    findEntity(6006).deletedAt = '2026-10-30T08:00:00Z'
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/requests')).body.data.map((row) => row.id)).not.toContain(9107)
  })

  it('shows staff identities and reasons to global readers only', async () => {
    seedMockSession(FOLLOWER)
    const detail = await call('GET', '/requests/9108')
    expect(keys(detail.body.data)).toEqual(
      [...ROW_KEYS, 'assignment_history', 'completion_requests', 'payment', 'record'].sort(),
    )
    expect(detail.body.data.payment).toEqual({ paid: true })
    expect(keys(detail.body.data.assignment_history[0])).toEqual([
      'assessor',
      'assigned_at',
      'assigned_by',
      'end_reason',
      'ended_at',
      'id',
      'reason',
      'started_at',
      'status',
    ])
    expect(detail.body.data.record.status_history.at(-1)).toMatchObject({
      verb: 'start_review',
      actor: 'staff',
    })
    seedMockSession(SULTAN)
    const own = await call('GET', '/requests/9108')
    expect(own.body.data.assignment_history).toHaveLength(1)
    expect(keys(own.body.data.assignment_history[0])).toEqual([
      'assessor',
      'assigned_at',
      'ended_at',
      'id',
      'started_at',
      'status',
    ])
    expect(own.body.data.record.status_history.every((step) => !('actor_staff' in step))).toBe(true)
    expect((await call('GET', '/requests/9105')).status).toBe(404)
  })

  it('lists eligible assessors without the caller and the current assessor', async () => {
    seedMockSession(FOLLOWING_ASSESSOR)
    const { body } = await call('GET', '/requests/assessors?assessment_id=9108')
    expect(keys(body.data[0])).toEqual(['id', 'name', 'open_requests'])
    expect(body.data.map((item) => item.id)).not.toContain(10)
    expect(body.data.map((item) => item.id)).not.toContain(12)
    seedMockSession(SULTAN)
    expect((await call('GET', '/requests/assessors')).status).toBe(403)
  })

  it('assigns a submitted request, refuses self-assignment, inactive assessors and the wrong state', async () => {
    seedMockSession(FOLLOWING_ASSESSOR)
    const self = await call('POST', '/requests/9105/assign', { assessor_id: 10 })
    expect([self.status, self.body.code]).toEqual([422, 'sod_self_assignment'])
    expect((await call('POST', '/requests/9105/assign', { assessor_id: 12 })).body.code).toBe(
      'assessor.invalid',
    )
    expect((await call('POST', '/requests/9105/assign', {})).status).toBe(422)
    const assigned = await call('POST', '/requests/9105/assign', { assessor_id: 11 })
    expect(assigned.body.data).toMatchObject({ status: 'assigned', assessor: { id: 11 } })
    const twice = await call('POST', '/requests/9105/assign', { assessor_id: 15 })
    expect([twice.status, twice.body.code]).toEqual([409, 'state.transition_illegal'])
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', '/requests/9105/reassign', { assessor_id: 15 })).status).toBe(422)
  })

  it('reassigns with a mandatory reason, keeps the status and the history', async () => {
    seedMockSession(FOLLOWER)
    expect(
      (await call('POST', '/requests/9108/reassign', { assessor_id: 11 })).body.errors,
    ).toHaveProperty('reason')
    expect(
      (await call('POST', '/requests/9108/reassign', { assessor_id: 10, reason: REASON })).body
        .code,
    ).toBe('assessor.same')
    const moved = await call('POST', '/requests/9108/reassign', { assessor_id: 11, reason: REASON })
    expect(moved.body.data).toMatchObject({ status: 'under_review', assessor: { id: 11 } })
    expect(moved.body.data.assignment_history.map((item) => item.status)).toEqual([
      'reassigned',
      'reassigned',
      'active',
    ])
    seedMockSession(SULTAN)
    expect((await call('GET', '/assessments/9108/review')).status).toBe(404)
  })
})

describe('accredited mock — assessor review', () => {
  it('lists the assigned tabs and gives the edit mode to the assignee only', async () => {
    seedMockSession(SULTAN)
    expect(
      (await call('GET', '/requests/assigned?filter[tab]=new')).body.data.map((row) => row.id),
    ).toEqual([9107])
    expect((await call('GET', '/requests/assigned?filter[tab]=done')).status).toBe(422)
    const review = await call('GET', '/assessments/9108/review')
    expect(keys(review.body.data)).toEqual(REVIEW_KEYS)
    expect(review.body.data.mode).toBe('edit')
    expect(review.body.data.evidence.every((file) => file.scan_status === 'clean')).toBe(true)
    expect(keys(review.body.data.feedback['101'])).toEqual([
      'improvement_points',
      'notes',
      'strengths',
    ])
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/assessments/9108/review')).body.data.mode).toBe('read_only')
    seedMockSession(HIND)
    expect((await call('GET', '/assessments/9108/review')).status).toBe(404)
    seedMockSession({ id: 6002, roles: ['entity'], entityId: 6002 })
    expect((await call('GET', '/assessments/9108/review')).status).toBe(403)
  })

  it('starts a review idempotently and saves partial scores and feedback', async () => {
    seedMockSession(SULTAN)
    expect((await call('POST', '/assessments/9107/start-review', {})).body.data.status).toBe(
      'under_review',
    )
    expect((await call('POST', '/assessments/9107/start-review', {})).status).toBe(200)
    const before = (await call('GET', '/assessments/9108/review')).body.data
    const [firstPoint] = Object.keys(before.scores.points)
    const saved = await call('PUT', '/assessments/9108/review/scores', {
      scores: { points: { [firstPoint]: null, 1002: '9.25' } },
    })
    expect(saved.body.data.scores.points).not.toHaveProperty(firstPoint)
    expect(saved.body.data.scores.points['1002']).toEqual({ raw_value: '9.25', percent: '92.50' })
    expect(
      (await call('PUT', '/assessments/9108/review/scores', { scores: { subs: { 101: '5' } } }))
        .body.code,
    ).toBe('score.use_points')
    expect(
      (await call('PUT', '/assessments/9108/review/scores', { scores: { subs: { 103: '11' } } }))
        .status,
    ).toBe(422)
    const feedback = await call('PUT', '/assessments/9108/review/feedback', {
      feedback: { 103: { strengths: 'مبادرات موثقة', notes: null } },
    })
    expect(feedback.body.data.feedback['103']).toEqual({
      strengths: 'مبادرات موثقة',
      improvement_points: null,
      notes: null,
    })
    expect(
      (
        await call('PUT', '/assessments/9108/review/feedback', {
          feedback: { 103: { praise: 'x' } },
        })
      ).status,
    ).toBe(422)
    expect(
      (await call('PUT', '/assessments/9106/review/scores', { scores: { subs: { 103: '5' } } }))
        .status,
    ).toBe(404)
  })

  it('requests completion with a required expiry outcome and completes with scores only', async () => {
    seedMockSession(SULTAN)
    const missingOutcome = await call('POST', '/assessments/9108/request-completion', {
      notes: 'يرجى استكمال الشواهد.',
      items: [{ sub_criterion_id: 103, kind: 'evidence' }],
      deadline_at: inDays(7),
    })
    expect(missingOutcome.body.errors).toHaveProperty('expiry_outcome')
    expect(
      (
        await call('POST', '/assessments/9108/request-completion', {
          notes: 'يرجى استكمال السيرة.',
          items: [{ sub_criterion_id: 702, kind: 'cv' }],
          deadline_at: inDays(7),
          expiry_outcome: 'decline',
        })
      ).body.code,
    ).toBe('cv.not_allowed')
    expect(
      (
        await call('POST', '/assessments/9108/request-completion', {
          notes: 'يرجى الاستكمال.',
          items: [{ sub_criterion_id: 103, kind: 'evidence' }],
          deadline_at: inDays(90),
          expiry_outcome: 'decline',
        })
      ).body.errors,
    ).toHaveProperty('deadline_at')
    const incomplete = await call('POST', '/assessments/9108/complete', {})
    expect([incomplete.status, incomplete.body.code]).toEqual([422, 'scores.incomplete'])
    const review = (await call('GET', '/assessments/9108/review')).body.data
    await call('PUT', '/assessments/9108/review/scores', { scores: allUnits(review.criteria) })
    const completed = await call('POST', '/assessments/9108/complete', {})
    expect(completed.body.data).toMatchObject({ status: 'completed', is_open: false })
    expect(completed.body.data.result.percent).toBe('80.00')
    expect((await call('GET', '/assessments/9108/review')).body.data.mode).toBe('read_only')
  })

  it('applies the expiry outcome at the deadline: continue keeps the review, decline closes the request', async () => {
    seedMockSession(SULTAN)
    await call('POST', '/assessments/9107/start-review', {})
    await call('POST', '/assessments/9107/request-completion', {
      notes: 'يرجى استكمال الشواهد.',
      items: [{ sub_criterion_id: 103, kind: 'evidence' }],
      deadline_at: inDays(3),
      expiry_outcome: 'decline',
    })
    findAssessment(9107).completionRequests.at(-1).deadlineAt = inDays(-1)
    findAssessment(9106).completionRequests[0].deadlineAt = inDays(-1)
    const before = notificationsStore.length
    seedMockSession(FOLLOWER)
    const detail = await call('GET', '/requests/9107')
    expect(detail.body.data.status).toBe('declined_incomplete')
    expect(detail.body.data.completion_requests.at(-1).status).toBe('expired_declined')
    expect(detail.body.data.record.status_history.at(-1)).toMatchObject({
      verb: 'expire_completion',
      actor: 'system',
      to: 'declined_incomplete',
    })
    expect((await call('GET', '/requests/9106')).body.data.status).toBe('under_review')
    expect(notificationsStore.slice(before).map((item) => item.code)).toContain('ACC-12')
    seedMockSession({ id: 6006, roles: ['entity'], entityId: 6006 })
    expect((await call('GET', '/assessments/9107/result')).body.data.available).toBe(false)
    expect(
      (await call('GET', '/assessments/accredited/current')).body.data.latest_closed.status,
    ).toBe('declined_incomplete')
  })
})

describe('accredited mock — evidence bundles', () => {
  it('prepares a large bundle in the background, tells the requester and serves it to the requester only', async () => {
    seedMockSession(FOLLOWER)
    const queued = await call('POST', '/assessments/9108/evidence/bundles')
    expect(queued.status).toBe(202)
    expect(keys(queued.body.data)).toEqual([
      'assessment_id',
      'expires_at',
      'file_count',
      'id',
      'is_downloadable',
      'ready_at',
      'requested_at',
      'size_bytes',
      'status',
    ])
    expect(queued.body.data).toMatchObject({
      status: 'preparing',
      is_downloadable: false,
      file_count: 16,
    })
    const notReady = await call(
      'POST',
      `/assessments/9108/evidence/bundles/${queued.body.data.id}/download`,
    )
    expect([notReady.status, notReady.body.code]).toEqual([409, 'evidence.bundle_not_ready'])
    runQueuedJobs()
    expect(notificationsStore.at(-1)).toMatchObject({
      code: 'EVD-01',
      link: `/admin/requests/9108/evidence-bundles/${queued.body.data.id}`,
    })
    expect(
      (await call('GET', `/assessments/9108/evidence/bundles/${queued.body.data.id}`)).body.data,
    ).toMatchObject({ status: 'ready', is_downloadable: true })
    expect(
      (await call('POST', `/assessments/9108/evidence/bundles/${queued.body.data.id}/download`))
        .body.data.file_name,
    ).toBe('ACC-2026-000008-evidence.zip')
    seedMockSession(SYSTEM_ADMIN)
    expect(
      (await call('GET', `/assessments/9108/evidence/bundles/${queued.body.data.id}`)).status,
    ).toBe(404)
    seedMockSession({ id: 6002, roles: ['entity'], entityId: 6002 })
    expect((await call('POST', '/assessments/9108/evidence/bundles')).status).toBe(403)
  })
})

describe('accredited mock — Super Admin overrides and settings', () => {
  it('lets only the Super Admin override scores, reopen and force with a reason of 20 characters', async () => {
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('POST', '/assessments/9102/reopen', { reason: REASON })).status).toBe(403)
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', '/assessments/9102/reopen', { reason: 'قصير' })).status).toBe(422)
    const overridden = await call('POST', '/assessments/9102/scores/override', {
      scores: { subs: { 103: '10' } },
      reason: REASON,
    })
    expect(overridden.status).toBe(200)
    expect(findAssessment(9102).result.version).toBe(2)
    expect(
      (
        await call('POST', '/assessments/9102/scores/override', {
          scores: { subs: { 103: null } },
          reason: REASON,
        })
      ).status,
    ).toBe(422)
    const reopened = await call('POST', '/assessments/9102/reopen', { reason: REASON })
    expect(reopened.body.data).toMatchObject({ status: 'under_review', result: null })
    expect(
      (
        await call('POST', '/assessments/9103/transition/force', {
          to_status: 'draft',
          reason: REASON,
        })
      ).body.code,
    ).toBe('state.transition_illegal')
    expect(
      (
        await call('POST', '/assessments/9105/transition/force', {
          to_status: 'under_review',
          reason: REASON,
        })
      ).body.code,
    ).toBe('assessment.assignment_required')
    expect(
      (
        await call('POST', '/assessments/9105/transition/force', {
          to_status: 'completed',
          reason: REASON,
        })
      ).body.code,
    ).toBe('assessment.result_required')
    expect(
      (
        await call('POST', '/assessments/9105/transition/force', {
          to_status: 'refunded',
          reason: REASON,
        })
      ).status,
    ).toBe(422)
    expect(
      (
        await call('POST', '/assessments/9105/transition/force', {
          to_status: 'draft',
          reason: REASON,
        })
      ).body.data.status,
    ).toBe('draft')
  })

  it('reads and updates the assessment settings with validation, a Super Admin reason and a due-date shift', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const { body } = await call('GET', '/settings/assessment')
    expect(keys(body.data)).toEqual([
      'accredited_review_sla_days',
      'accredited_review_sla_limits',
      'advanced_disqualification_score',
      'advanced_initial_review_sla_days',
      'advanced_min_entity_age_years',
      'committee_entity_data_visibility',
      'evidence_allowed_types',
      'evidence_max_files_per_sub',
      'evidence_type_catalog',
      'general_upload_max_file_mb',
      'upload_max_file_mb',
    ])
    expect(body.data).toMatchObject({
      advanced_min_entity_age_years: 3,
      advanced_disqualification_score: '60.00',
      advanced_initial_review_sla_days: 14,
      committee_entity_data_visibility: 'name_only',
    })
    expect(
      (await call('PUT', '/settings/assessment', { advanced_disqualification_score: '100.5' }))
        .status,
    ).toBe(422)
    expect(
      (
        await call('PUT', '/settings/assessment', {
          committee_entity_data_visibility: 'everything',
        })
      ).status,
    ).toBe(422)
    const advanced = await call('PUT', '/settings/assessment', {
      advanced_disqualification_score: '65.5',
      advanced_min_entity_age_years: 5,
      committee_entity_data_visibility: 'code_only',
    })
    expect(advanced.body.data).toMatchObject({
      advanced_disqualification_score: '65.50',
      advanced_min_entity_age_years: 5,
      committee_entity_data_visibility: 'code_only',
    })
    expect(
      (await call('PUT', '/settings/assessment', { accredited_review_sla_days: 121 })).status,
    ).toBe(422)
    expect((await call('PUT', '/settings/assessment', { evidence_allowed_types: [] })).status).toBe(
      422,
    )
    const dueBefore = findAssessment(9108).dueAt
    const updated = await call('PUT', '/settings/assessment', {
      accredited_review_sla_days: 21,
      evidence_allowed_types: ['pdf'],
    })
    expect(updated.body.data).toMatchObject({
      accredited_review_sla_days: 21,
      evidence_allowed_types: ['pdf'],
    })
    runQueuedJobs()
    expect(new Date(findAssessment(9108).dueAt) - new Date(dueBefore)).toBe(7 * 86400000)
    seedMockSession(SUPER_ADMIN)
    expect((await call('PUT', '/settings/assessment', { upload_max_file_mb: 30 })).body.code).toBe(
      'reason_required',
    )
    seedMockSession(SULTAN)
    expect((await call('GET', '/settings/assessment')).status).toBe(403)
  })
})
