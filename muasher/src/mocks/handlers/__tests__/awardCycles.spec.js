import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { findAssessment, resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { findCycle } from '@/mocks/stores/award/cycleStore'
import { notificationsStore } from '@/mocks/stores/notificationsStore'
import { discardQueuedJobs } from '@/mocks/stores/mockQueue'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)

const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const SECRETARY = { id: 18, roles: ['award_secretary'] }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const FILTERING_OFFICER = { id: 13, roles: ['filtering_officer'] }
const ASSESSOR = { id: 10, roles: ['assessor'] }
const OTHER_ASSESSOR = { id: 27, roles: ['assessor'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }
const REASON = 'إجراء معتمد من إدارة الجائزة بعد مراجعة المواعيد.'

const CYCLE_KEYS = [
  'announcement_at', 'closed_at', 'committee_end', 'committee_start', 'conditions', 'counts', 'created_at', 'criteria_version_id', 'id', 'initial_review_sla_days',
  'name', 'opened_at', 'participation_certificate_enabled', 'regions', 'scale_kind', 'status', 'submission_end', 'submission_start', 'submissions_closed_at', 'updated_at', 'year',
]
const PUBLIC_KEYS = ['announcement_at', 'committee_end', 'committee_start', 'conditions', 'id', 'is_accepting_applications', 'name', 'regions', 'status', 'submission_end', 'submission_start', 'year']

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

function newCycleBody(overrides = {}) {
  return {
    name: { ar: 'دورة الجائزة للمناطق الشمالية', en: 'Award for the Northern Regions' },
    submission_start: inDays(300),
    submission_end: inDays(340),
    committee_start: inDays(350),
    committee_end: inDays(380),
    region_ids: [6, 7],
    criteria_version_id: 1,
    ...overrides,
  }
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

describe('award cycles mock — reads and scope', () => {
  it('answers the public running cycle without staff or score data', async () => {
    const { status, body } = await call('GET', '/award/cycles/current')
    expect(status).toBe(200)
    expect(keys(body.data)).toEqual(PUBLIC_KEYS)
    expect(body.data).toMatchObject({ id: 31, status: 'submission_open', is_accepting_applications: true, conditions: { min_entity_age_years: 3 } })
    expect(keys(body.data.conditions)).toEqual(['min_entity_age_years'])
    expect(body.data.regions.map((region) => region.code)).toEqual(['riyadh', 'makkah', 'madinah', 'eastern'])
  })

  it('lists every cycle with counts and stats to global readers and only the member cycles to others', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const { body } = await call('GET', '/award/cycles')
    expect(body.data.map((cycle) => cycle.id)).toEqual([32, 31, 30])
    expect(keys(body.data[0])).toEqual(CYCLE_KEYS)
    expect(body.data[1].counts).toEqual({ requests: 5, qualified: 0 })
    expect(body.meta.stats).toMatchObject({ total: 3, by_status: { draft: 1, submission_open: 1, closed: 1 } })
    expect(body.meta.pagination).toMatchObject({ page: 1, total: 3 })
    seedMockSession(FILTERING_OFFICER)
    expect((await call('GET', '/award/cycles')).body.data.map((cycle) => cycle.id)).toEqual([31, 30])
    seedMockSession(ASSESSOR)
    expect((await call('GET', '/award/cycles')).body.data.map((cycle) => cycle.id)).toEqual([31, 30])
    seedMockSession(OTHER_ASSESSOR)
    expect((await call('GET', '/award/cycles')).body.data).toEqual([])
    expect((await call('GET', '/award/cycles/31')).status).toBe(404)
    seedMockSession(ENTITY)
    expect((await call('GET', '/award/cycles')).status).toBe(403)
  })

  it('filters, searches, sorts and refuses unknown filters', async () => {
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/award/cycles?filter[status]=closed')).body.data.map((cycle) => cycle.id)).toEqual([30])
    expect((await call('GET', '/award/cycles?search=Next')).body.data.map((cycle) => cycle.id)).toEqual([32])
    expect((await call('GET', '/award/cycles?sort=submission_start')).body.data[0].id).toBe(30)
    expect((await call('GET', '/award/cycles?filter[status]=judging')).status).toBe(422)
    expect((await call('GET', '/award/cycles?sort=name')).status).toBe(422)
  })

  it('reveals members and initial assessors to managers and global readers only', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const admin = (await call('GET', '/award/cycles/31')).body.data
    expect(admin.can_manage).toBe(true)
    expect(admin.members.map((member) => [member.id, member.role])).toEqual([
      [13, 'filtering_officer'],
      [8, 'filtering_officer'],
      [18, 'award_secretary'],
    ])
    expect(admin.initial_assessors.find((link) => link.id === 16)).toMatchObject({ is_active: false })
    seedMockSession(FILTERING_OFFICER)
    const officer = (await call('GET', '/award/cycles/31')).body.data
    expect(officer.can_manage).toBe(false)
    expect(officer).not.toHaveProperty('members')
    expect(officer).not.toHaveProperty('initial_assessors')
  })

  it('serves the default conditions and the candidates per role to managers only', async () => {
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('GET', '/award/cycles/default-conditions')).body.data).toEqual({ min_entity_age_years: 3, disqualification_score: '60.00', initial_review_sla_days: 14 })
    const officers = (await call('GET', '/award/cycles/candidates?role=filtering_officer')).body.data
    expect(officers.map((person) => person.id).sort((a, b) => a - b)).toEqual([8, 13])
    expect(keys(officers[0])).toEqual(['id', 'name'])
    expect((await call('GET', '/award/cycles/candidates?role=initial_assessor')).body.data.map((person) => person.id).sort((a, b) => a - b)).toEqual([10, 11, 15, 16, 27])
    expect((await call('GET', '/award/cycles/candidates?role=juror')).status).toBe(422)
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/award/cycles/candidates?role=filtering_officer')).status).toBe(403)
  })
})

describe('award cycles mock — create and edit', () => {
  it('creates a draft with the default conditions and makes a creating secretary a member', async () => {
    seedMockSession(SECRETARY)
    const { status, body } = await call('POST', '/award/cycles', newCycleBody())
    expect(status).toBe(201)
    expect(body.data).toMatchObject({ status: 'draft', conditions: { min_entity_age_years: 3, disqualification_score: '60.00' }, initial_review_sla_days: 14, participation_certificate_enabled: false, can_manage: true })
    expect(body.data.members).toEqual([{ id: 18, name: expect.any(String), role: 'award_secretary' }])
  })

  it('refuses invalid bodies, unordered dates, an unpublished version and a reserved setting', async () => {
    seedMockSession(SECRETARY)
    const invalid = await call('POST', '/award/cycles', { name: { ar: '' } })
    expect(invalid.status).toBe(422)
    expect(Object.keys(invalid.body.errors)).toEqual(expect.arrayContaining(['name.ar', 'name.en', 'submission_start', 'region_ids', 'criteria_version_id']))
    expect((await call('POST', '/award/cycles', newCycleBody({ committee_start: inDays(320) }))).body.errors).toHaveProperty('committee_start')
    expect((await call('POST', '/award/cycles', newCycleBody({ criteria_version_id: 999 }))).body.code).toBe('cycle.criteria_version_not_published')
    const reserved = await call('POST', '/award/cycles', newCycleBody({ participation_certificate_enabled: true }))
    expect(reserved).toMatchObject({ status: 403, body: { code: 'cycle.participation_setting_reserved' } })
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', '/award/cycles', newCycleBody())).body.code).toBe('reason_required')
    expect((await call('POST', '/award/cycles', newCycleBody({ reason: REASON, participation_certificate_enabled: true }))).status).toBe(201)
  })

  it('locks the stage fields of a running cycle and re-checks the dates as a whole', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const locked = await call('PUT', '/award/cycles/31', { submission_start: inDays(-40), criteria_version_id: 1 })
    expect(locked).toMatchObject({ status: 409, body: { code: 'cycle.field_locked', meta: { locked_fields: ['submission_start', 'criteria_version_id'] } } })
    expect((await call('PUT', '/award/cycles/31', { submission_end: inDays(-1) })).body.code).toBe('cycle.dates_unordered')
    expect((await call('PUT', '/award/cycles/31', { region_ids: [1, 2, 3] })).body.code).toBe('cycle.region_has_requests')
    const updated = await call('PUT', '/award/cycles/31', { disqualification_score: '65.5', region_ids: [1, 2, 3, 4, 5] })
    expect(updated.body.data.conditions.disqualification_score).toBe('65.50')
    expect(updated.body.data.regions).toHaveLength(5)
    expect((await call('PUT', '/award/cycles/31', { status: 'closed' })).status).toBe(422)
    expect((await call('PUT', '/award/cycles/30', { name: { ar: 'اسم', en: 'Name' } })).body.code).toBe('cycle.field_locked')
  })

  it('lets a secretary manage only the cycles of their membership', async () => {
    seedMockSession(SECRETARY)
    expect((await call('PUT', '/award/cycles/31', { announcement_at: inDays(130) })).status).toBe(200)
    findCycle(31).members = findCycle(31).members.filter((member) => member.userId !== 18)
    expect((await call('PUT', '/award/cycles/31', { announcement_at: inDays(131) })).status).toBe(404)
    seedMockSession(FILTERING_OFFICER)
    expect((await call('PUT', '/award/cycles/31', { announcement_at: inDays(131) })).status).toBe(403)
  })

  it('replaces the members and the initial assessors with role and segregation checks', async () => {
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('PUT', '/award/cycles/31/members', {})).status).toBe(422)
    expect((await call('PUT', '/award/cycles/31/members', { filtering_officer_ids: [10] })).body.code).toBe('cycle.member_invalid')
    const members = await call('PUT', '/award/cycles/31/members', { filtering_officer_ids: [13] })
    expect(members.body.data.members.filter((member) => member.role === 'filtering_officer').map((member) => member.id)).toEqual([13])
    expect(members.body.data.members.some((member) => member.role === 'award_secretary')).toBe(true)
    expect((await call('PUT', '/award/cycles/31/initial-assessors', { assessor_ids: [13] })).body.code).toBe('cycle.initial_assessor_invalid')
    const linked = await call('PUT', '/award/cycles/31/initial-assessors', { assessor_ids: [10, 27] })
    expect(linked.body.data.initial_assessors.filter((link) => link.is_active).map((link) => link.id)).toEqual([10, 27])
    findCycle(31).members.push({ userId: 27, role: 'filtering_officer' })
    expect((await call('PUT', '/award/cycles/31/initial-assessors', { assessor_ids: [27] })).body.code).toBe('sod_conflict')
    expect((await call('PUT', '/award/cycles/30/members', { filtering_officer_ids: [13] })).status).toBe(409)
  })
})

describe('award cycles mock — stages', () => {
  it('refuses to open a draft while another cycle runs and opens it once the running one closes', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const refused = await call('POST', '/award/cycles/32/open', {})
    expect(refused).toMatchObject({ status: 409, body: { code: 'cycle.another_open', meta: { award_cycle_id: 31 } } })
    findCycle(31).status = 'closed'
    const opened = await call('POST', '/award/cycles/32/open', {})
    expect(opened.body.data).toMatchObject({ status: 'submission_open' })
    expect(Date.parse(opened.body.data.submission_start)).toBeLessThanOrEqual(Date.now())
    expect(notificationsStore.some((item) => item.code === 'ADV-01')).toBe(true)
  })

  it('closes the submissions early, expires the drafts and blocks the close while requests are open', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const draft = await (async () => {
      seedMockSession(ENTITY)
      return (await call('POST', '/assessments/advanced', { award_cycle_id: 31 })).body.data
    })()
    seedMockSession(SYSTEM_ADMIN)
    const closed = await call('POST', '/award/cycles/31/close-submissions', {})
    expect(closed.body.data.status).toBe('submission_closed')
    expect(findAssessment(draft.id).status).toBe('expired')
    expect(notificationsStore.some((item) => item.code === 'CYC-01')).toBe(true)
    const blocked = await call('POST', '/award/cycles/31/close', {})
    expect(blocked).toMatchObject({ status: 409, body: { code: 'cycle.requests_pending', meta: { open_requests_count: 5 } } })
    expect(keys(blocked.body.meta.open_requests[0])).toEqual(['id', 'number', 'status'])
    expect((await call('POST', '/award/cycles/31/close-submissions', {})).status).toBe(409)
  })

  it('lets only the Super Admin reopen a closed cycle with a reason of 20 characters', async () => {
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('POST', '/award/cycles/30/reopen', { reason: REASON })).status).toBe(403)
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', '/award/cycles/30/reopen', { reason: 'قصير' })).status).toBe(422)
    expect((await call('POST', '/award/cycles/30/reopen', { reason: REASON })).body.code).toBe('cycle.another_open')
    findCycle(31).status = 'closed'
    expect((await call('POST', '/award/cycles/30/reopen', { reason: REASON })).body.data.status).toBe('announced')
    expect((await call('POST', '/award/cycles/32/open', {})).body.code).toBe('reason_required')
  })
})
