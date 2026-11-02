import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { findEntity } from '@/mocks/stores/entitiesStore'
import { findAssessment, resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { findCycle } from '@/mocks/stores/award/cycleStore'
import { notificationsStore } from '@/mocks/stores/notificationsStore'
import { discardQueuedJobs } from '@/mocks/stores/mockQueue'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)

const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const NAMAA = { id: 6001, roles: ['entity'], entityId: 6001 }
const RUWAD = { id: 6005, roles: ['entity'], entityId: 6005 }
const TAMKEEN = { id: 6006, roles: ['entity'], entityId: 6006 }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const ASSESSOR = { id: 10, roles: ['assessor'] }
const OUTSIDE_ASSESSOR = { id: 27, roles: ['assessor'] }
const FILTERING_OFFICER = { id: 13, roles: ['filtering_officer'] }
const SECOND_OFFICER = { id: 8, roles: ['request_follower', 'filtering_officer'] }
const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const NOTES = 'يرجى استكمال وصف منهجية العمل وإرفاق التقرير المحدّث قبل المهلة.'
const REASON = 'سحب الطلب بناءً على خطاب رسمي من الجهة.'
const OVERRIDE = 'أظهرت الشواهد أثرًا مجتمعيًا واضحًا يبرر التأهيل استثناءً.'

const CURRENT_KEYS = ['cycle', 'eligibility', 'previous', 'request']
const FILTERING_ROW_KEYS = ['decision', 'entity', 'id', 'initial_assessed_at', 'initial_result', 'is_frozen', 'meets_threshold', 'number', 'region', 'status', 'submitted_at']
const DECISION_KEYS = ['decided_at', 'decided_by', 'id', 'initial_percent', 'is_override', 'note', 'outcome', 'reason', 'threshold_percent']

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

function closeSubmissions() {
  findCycle(31).status = 'submission-closed'
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCriteriaStore()
  resetEntitiesMock()
  resetAssessmentsStore()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  discardQueuedJobs()
})
afterAll(() => server.close())

describe('advanced path mock — entity', () => {
  it('answers the eligibility with every condition, the running cycle and the previous request', async () => {
    seedMockSession(ALBIRR)
    const { status, body } = await call('GET', '/assessments/advanced/current')
    expect(status).toBe(200)
    expect(keys(body.data)).toEqual(CURRENT_KEYS)
    expect(body.data.eligibility).toEqual({
      eligible: true,
      window_open: true,
      conditions: [
        { rule: 'min_entity_age', passed: true, required: 3, actual: expect.any(Number) },
        { rule: 'region', passed: true, required: null, actual: null },
      ],
    })
    expect(body.data.request).toBeNull()
    expect(body.data.previous).toMatchObject({ id: 9207, path: 'advanced', status: 'not_qualified', award_cycle_id: 30, condition_failure: null })
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/assessments/advanced/current')).status).toBe(403)
  })

  it('applies with 201, returns the existing draft with 200 and refuses a second request in the cycle', async () => {
    seedMockSession(ALBIRR)
    const created = await call('POST', '/assessments/advanced', { award_cycle_id: 31 })
    expect(created.status).toBe(201)
    expect(created.body.data).toMatchObject({ path: 'advanced', status: 'draft', award_cycle_id: 31, number: expect.stringMatching(/^ADV-\d{4}-\d{6}$/) })
    const again = await call('POST', '/assessments/advanced', { award_cycle_id: 31 })
    expect(again).toMatchObject({ status: 200, body: { data: { id: created.body.data.id } } })
    seedMockSession(RUWAD)
    expect((await call('POST', '/assessments/advanced', { award_cycle_id: 31 })).body).toMatchObject({ code: 'advanced.request_exists', meta: { assessment_id: 9204 } })
    expect((await call('POST', '/assessments/advanced', { award_cycle_id: 32 })).body.code).toBe('cycle.submission_closed')
    expect((await call('POST', '/assessments/advanced', {})).status).toBe(422)
  })

  it('records one conditions rejection and answers 422 with the failed conditions', async () => {
    findCycle(31).minEntityAgeYears = 30
    seedMockSession(ALBIRR)
    const rejected = await call('POST', '/assessments/advanced', { award_cycle_id: 31 }, 'ar')
    expect(rejected).toMatchObject({ status: 422, body: { code: 'cycle.conditions_failed', meta: { conditions: [{ rule: 'min_entity_age', passed: false, required: 30 }] } } })
    expect(rejected.body.errors.conditions[0]).toContain('30')
    const current = (await call('GET', '/assessments/advanced/current')).body.data
    expect(current.request).toMatchObject({ status: 'rejected_conditions', condition_failure: { failures: [{ rule: 'min_entity_age', passed: false }] } })
    await call('POST', '/assessments/advanced', { award_cycle_id: 31 })
    expect(notificationsStore.filter((item) => item.code === 'ADV-03')).toHaveLength(1)
    findCycle(31).minEntityAgeYears = 3
    expect((await call('POST', '/assessments/advanced', { award_cycle_id: 31 })).status).toBe(201)
  })

  it('shows the seeded region rejection to an entity outside the cycle regions', async () => {
    seedMockSession(TAMKEEN)
    const current = (await call('GET', '/assessments/advanced/current')).body.data
    expect(current.eligibility.eligible).toBe(false)
    expect(current.request).toMatchObject({ id: 9206, status: 'rejected_conditions', condition_failure: { failures: [{ rule: 'region' }] } })
  })

  it('submits a complete draft after re-checking the conditions and the window, then deletes only drafts', async () => {
    seedMockSession(ALBIRR)
    const draft = (await call('POST', '/assessments/advanced', { award_cycle_id: 31 })).body.data
    expect((await call('POST', `/assessments/${draft.id}/submit`, {})).body.code).toBe('submission.incomplete')
    const record = findAssessment(draft.id)
    Object.assign(record, { cv: { ...findAssessment(9203).cv }, evidence: findAssessment(9203).evidence.map((row) => ({ ...row })) })
    findCycle(31).regionIds = [2, 3, 4]
    expect((await call('POST', `/assessments/${draft.id}/submit`, {})).body.code).toBe('cycle.conditions_failed')
    findCycle(31).regionIds = [1, 2, 3, 4]
    const submitted = await call('POST', `/assessments/${draft.id}/submit`, {})
    expect(submitted.body.data).toMatchObject({ status: 'awaiting_review_assignment', is_open: true })
    expect(notificationsStore.some((item) => item.code === 'ADV-04' && item.recipientRole === 'request_follower')).toBe(true)
    expect((await call('DELETE', `/assessments/${draft.id}`, {})).status).toBe(409)
  })

  it('deletes its own draft only, and refuses edits outside the submission window', async () => {
    seedMockSession(ALBIRR)
    const draft = (await call('POST', '/assessments/advanced', { award_cycle_id: 31 })).body.data
    seedMockSession(NAMAA)
    expect((await call('DELETE', `/assessments/${draft.id}`, {})).status).toBe(404)
    seedMockSession(ALBIRR)
    findCycle(31).status = 'submission-closed'
    expect((await call('PUT', `/assessments/${draft.id}/cv`, { cv: { 101: 'سيرة' } })).body.code).toBe('cycle.submission_closed')
    findCycle(31).status = 'submission-open'
    expect((await call('DELETE', `/assessments/${draft.id}`, {})).status).toBe(204)
    expect(findAssessment(draft.id)).toBeNull()
    expect((await call('DELETE', '/assessments/9102', {})).status).toBe(403)
  })

  it('lets a returned request edit only the listed items and resubmit to the follower queue', async () => {
    seedMockSession(NAMAA)
    const form = (await call('GET', '/assessments/9201/form')).body.data
    expect(form.completion_request).toMatchObject({ status: 'open', expiry_outcome: 'continue_as_submitted', items: [{ code: 'M2.1', kind: 'cv' }, { code: 'M8.2', kind: 'evidence' }] })
    expect((await call('PUT', '/assessments/9201/cv', { cv: { 202: 'سيرة أخرى' } })).body.code).toBe('completion.item_not_requested')
    expect((await call('PUT', '/assessments/9201/cv', { cv: { 201: 'وصف دراسة الاحتياجات ومنهجيتها.' } })).status).toBe(200)
    findAssessment(9201).evidence.push({ ...findAssessment(9203).evidence.find((row) => row.subCode === 'M8.2'), id: '5a3c7e21-9201-4c8d-8e9f-000000000099' })
    const resubmitted = await call('POST', '/assessments/9201/submit', {})
    expect(resubmitted.body.data.status).toBe('awaiting_review_assignment')
    expect(findAssessment(9201).completionRequests[0].status).toBe('resubmitted')
  })

  it('masks the filtering states as under review for the entity and hides the history rows', async () => {
    seedMockSession(RUWAD)
    const record = (await call('GET', '/assessments/9204')).body.data
    expect(record).toMatchObject({ status: 'under_review', is_open: true })
    expect(record.status_history.map((step) => step.to)).not.toContain('awaiting_filtering')
    expect((await call('GET', '/assessments/9204/result')).body.data).toMatchObject({ status: 'under_review', available: false })
    findCycle(31).announcedAt = new Date().toISOString()
    expect((await call('GET', '/assessments/9204')).body.data.status).toBe('awaiting_filtering')
  })
})

describe('advanced path mock — follower and initial assessor', () => {
  it('lists both paths with cycle and region, filters by path and cycle and validates the statuses', async () => {
    seedMockSession(FOLLOWER)
    const advanced = await call('GET', '/requests?filter[path]=advanced')
    expect(advanced.body.data.every((row) => row.path === 'advanced')).toBe(true)
    const row = advanced.body.data.find((item) => item.id === 9203)
    expect(row).toMatchObject({ status: 'awaiting_review_assignment', award_cycle: { id: 31, status: 'submission_open' }, region: { code: 'madinah' } })
    expect(advanced.body.meta.stats.by_status).toMatchObject({ awaiting_review_assignment: 1, needs_completion: 1, under_initial_assessment: 1, awaiting_filtering: 2 })
    expect((await call('GET', '/requests?filter[cycle_id]=30')).body.data.map((item) => item.id).sort()).toEqual([9207, 9208, 9209])
    expect((await call('GET', '/requests?filter[path]=accredited&filter[status]=awaiting_filtering')).status).toBe(422)
    expect((await call('GET', '/requests?filter[path]=self')).status).toBe(422)
  })

  it('returns a request for completion with the committee start as the latest deadline', async () => {
    seedMockSession(FOLLOWER)
    const body = { notes: NOTES, items: [{ sub_criterion_id: 201, kind: 'cv' }], deadline_at: inDays(5), expiry_outcome: 'decline' }
    expect((await call('POST', '/requests/9203/return-for-completion', { ...body, notes: 'قصير' })).status).toBe(422)
    const late = await call('POST', '/requests/9203/return-for-completion', { ...body, deadline_at: inDays(90) })
    expect(late).toMatchObject({ status: 422, body: { code: 'completion.deadline_after_committee_start' } })
    expect(late.body.meta.committee_start).toBe(findCycle(31).committeeStart)
    const returned = await call('POST', '/requests/9203/return-for-completion', body)
    expect(returned.body.data).toMatchObject({ status: 'needs_completion', completion_requests: [{ status: 'open', expiry_outcome: 'decline' }] })
    expect(notificationsStore.some((item) => item.code === 'ADV-25')).toBe(true)
    expect((await call('POST', '/requests/9203/return-for-completion', body)).status).toBe(409)
    expect((await call('POST', '/requests/9105/return-for-completion', body)).status).toBe(404)
    seedMockSession(ASSESSOR)
    expect((await call('POST', '/requests/9202/return-for-completion', body)).status).toBe(403)
  })

  it('applies the expiry outcome of an advanced return', async () => {
    findAssessment(9201).completionRequests[0].deadlineAt = inDays(-1)
    seedMockSession(FOLLOWER)
    const detail = (await call('GET', '/requests/9201')).body.data
    expect(detail.status).toBe('awaiting_review_assignment')
    expect(detail.completion_requests[0].status).toBe('expired_continued')
    expect(notificationsStore.some((item) => item.code === 'ADV-27')).toBe(true)
  })

  it('withdraws with a mandatory reason from every open submitted state, frozen requests included', async () => {
    seedMockSession(FOLLOWER)
    expect((await call('POST', '/requests/9202/withdraw', {})).status).toBe(422)
    const withdrawn = await call('POST', '/requests/9202/withdraw', { reason: REASON })
    expect(withdrawn.body.data.status).toBe('withdrawn')
    expect(withdrawn.body.data.assignment_history[0]).toMatchObject({ status: 'ended' })
    expect(findAssessment(9202).withdrawnAt).not.toBeNull()
    findEntity(7005).deletedAt = '2026-10-30T08:00:00Z'
    expect((await call('POST', '/requests/9205/withdraw', { reason: REASON })).body.data.status).toBe('withdrawn')
    expect((await call('POST', '/requests/9207/withdraw', { reason: REASON })).status).toBe(409)
  })

  it('assigns only the active initial assessors of the cycle who are not members, with the cycle deadline', async () => {
    seedMockSession(FOLLOWER)
    const eligible = (await call('GET', '/requests/assessors?assessment_id=9203')).body.data.map((person) => person.id)
    expect(eligible.sort((a, b) => a - b)).toEqual([10, 11, 15])
    expect((await call('POST', '/requests/9203/assign', { assessor_id: 27 })).body.code).toBe('assessor.not_in_cycle')
    findCycle(31).members.push({ userId: 15, role: 'filtering_officer' })
    expect((await call('POST', '/requests/9203/assign', { assessor_id: 15 })).body.code).toBe('sod_conflict')
    const assigned = await call('POST', '/requests/9203/assign', { assessor_id: 11 })
    expect(assigned.body.data).toMatchObject({ status: 'assigned', assessor: { id: 11 } })
    const days = (Date.parse(assigned.body.data.due_at) - Date.parse(assigned.body.data.assigned_at)) / 86400000
    expect(Math.round(days)).toBe(14)
    expect(notificationsStore.some((item) => item.code === 'ADV-05')).toBe(true)
  })

  it('runs the shared review engine to the filtering, without the assessor completion request', async () => {
    seedMockSession(ASSESSOR)
    const review = (await call('GET', '/assessments/9202/review')).body.data
    expect(review.mode).toBe('edit')
    expect((await call('POST', '/assessments/9202/request-completion', { notes: NOTES, items: [{ sub_criterion_id: 201, kind: 'cv' }], deadline_at: inDays(3), expiry_outcome: 'decline' })).status).toBe(409)
    await call('PUT', '/assessments/9202/review/scores', { scores: allUnits(review.criteria) })
    const completed = await call('POST', '/assessments/9202/complete', {})
    expect(completed.body.data).toMatchObject({ status: 'awaiting_filtering', result: null })
    expect(notificationsStore.some((item) => item.code === 'ADV-06')).toBe(true)
    seedMockSession(OUTSIDE_ASSESSOR)
    expect((await call('GET', '/assessments/9202/review')).status).toBe(404)
  })

  it('hides the filtering outcome from an assessor who does not read results', async () => {
    findAssessment(9204).status = 'qualified-for-committee'
    seedMockSession(ASSESSOR)
    expect((await call('GET', '/requests/assigned?filter[tab]=completed')).body.data.find((row) => row.id === 9204).status).toBe('awaiting_filtering')
  })
})

describe('advanced path mock — filtering', () => {
  it('lists the cycle filtering queue with the threshold, counts and the exact row shape', async () => {
    seedMockSession(FILTERING_OFFICER)
    const { body } = await call('GET', '/award/cycles/31/filtering')
    expect(body.data.map((row) => row.id)).toEqual([9204, 9205])
    expect(keys(body.data[0])).toEqual(FILTERING_ROW_KEYS)
    expect(body.data[0]).toMatchObject({ meets_threshold: true, region: { code: 'riyadh' } })
    expect(body.data[1]).toMatchObject({ meets_threshold: false })
    expect(body.meta).toMatchObject({ disqualification_score: '60.00', counts: { awaiting_filtering: 2, qualified_for_committee: 0, not_qualified: 0 }, below_threshold: 1 })
    expect((await call('GET', '/award/cycles/31/filtering?sort=initial_percent')).body.data[0].id).toBe(9205)
    expect((await call('GET', '/award/cycles/31/filtering?filter[region_id]=2')).body.data.map((row) => row.id)).toEqual([9205])
    expect((await call('GET', '/award/cycles/31/filtering?filter[status]=draft')).status).toBe(422)
  })

  it('scopes the filtering to the members of the cycle and the System Administrator', async () => {
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('GET', '/award/cycles/31/filtering')).status).toBe(200)
    seedMockSession({ id: 14, roles: ['filtering_officer'] })
    expect((await call('GET', '/award/cycles/31/filtering')).status).toBe(404)
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/award/cycles/31/filtering')).status).toBe(403)
    seedMockSession(FILTERING_OFFICER)
    expect((await call('GET', '/award/cycles/31/filtering/9203')).status).toBe(404)
  })

  it('shows the read-only initial assessment with the result breakdown and the decisions', async () => {
    seedMockSession(FILTERING_OFFICER)
    const detail = (await call('GET', '/award/cycles/31/filtering/9205')).body.data
    expect(keys(detail)).toEqual(['assessor', 'decisions', 'disqualification_score', 'result', 'review', 'row'])
    expect(detail.review.mode).toBe('read_only')
    expect(detail.result).toMatchObject({ available: true, main: expect.any(Array), feedback: expect.any(Array) })
    expect(detail.assessor).toMatchObject({ id: 11 })
    seedMockSession(ALBIRR)
    expect((await call('GET', '/award/cycles/31/filtering/9205')).status).toBe(403)
  })

  it('opens the decisions only after the submissions close and needs a reason below the threshold', async () => {
    seedMockSession(FILTERING_OFFICER)
    expect((await call('POST', '/award/cycles/31/filtering/9204/qualify', {})).body.code).toBe('cycle.filtering_not_open')
    closeSubmissions()
    const below = await call('POST', '/award/cycles/31/filtering/9205/qualify', {})
    expect(below).toMatchObject({ status: 422, body: { code: 'filtering.override_reason_required', meta: { threshold: '60.00' } } })
    expect((await call('POST', '/award/cycles/31/filtering/9205/qualify', { override_reason: 'قصير' })).status).toBe(422)
    const override = await call('POST', '/award/cycles/31/filtering/9205/qualify', { override_reason: OVERRIDE, note: 'ملاحظة للأمانة' })
    expect(override.body.data).toMatchObject({ status: 'qualified_for_committee', decision: { outcome: 'qualified', is_override: true, reason: OVERRIDE, threshold_percent: '60.00' } })
    expect(keys(override.body.data.decision)).toEqual(DECISION_KEYS)
    expect(notificationsStore.filter((item) => item.code === 'ADV-28').map((item) => item.recipientRole).sort()).toEqual(['award_secretary', 'system_admin'])
    const qualified = await call('POST', '/award/cycles/31/filtering/9204/qualify', { override_reason: OVERRIDE })
    expect(qualified.body.data.decision).toMatchObject({ is_override: false, reason: null })
    expect((await call('POST', '/award/cycles/31/filtering/9204/qualify', {})).status).toBe(409)
  })

  it('disqualifies with a mandatory reason and keeps the decision to the filtering officers of the cycle', async () => {
    closeSubmissions()
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('POST', '/award/cycles/31/filtering/9205/disqualify', { reason: REASON })).status).toBe(403)
    seedMockSession(SECOND_OFFICER)
    expect((await call('POST', '/award/cycles/31/filtering/9205/disqualify', {})).status).toBe(422)
    const done = await call('POST', '/award/cycles/31/filtering/9205/disqualify', { reason: REASON })
    expect(done.body.data).toMatchObject({ status: 'not_qualified', decision: { outcome: 'not_qualified', reason: REASON } })
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', '/award/cycles/31/filtering/9204/qualify', {})).body.code).toBe('reason_required')
  })

  it('refuses a decision by the initial assessor of the request and on a frozen request', async () => {
    closeSubmissions()
    findCycle(31).members.push({ userId: 11, role: 'filtering_officer' })
    seedMockSession({ id: 11, roles: ['filtering_officer', 'assessor'] })
    expect((await call('POST', '/award/cycles/31/filtering/9205/disqualify', { reason: REASON })).body.code).toBe('sod_conflict')
    findEntity(6005).deletedAt = '2026-10-30T08:00:00Z'
    seedMockSession(FILTERING_OFFICER)
    expect((await call('POST', '/award/cycles/31/filtering/9204/qualify', {})).body.code).toBe('entity.deleted')
  })
})
