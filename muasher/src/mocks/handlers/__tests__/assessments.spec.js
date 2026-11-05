import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { assessmentsHandlers } from '@/mocks/handlers/assessments'
import { criteriaHandlers } from '@/mocks/handlers/criteria'
import { entityLifecycleHandlers } from '@/mocks/handlers/entityLifecycle'
import { searchHandlers } from '@/mocks/handlers/search'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { discardQueuedJobs, runQueuedJobs } from '@/mocks/stores/mockQueue'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(
  ...assessmentsHandlers,
  ...criteriaHandlers,
  ...entityLifecycleHandlers,
  ...searchHandlers,
)

const OWNER = { id: 5001, roles: ['entity'], entityId: 5001 }
const OTHER_ENTITY = { id: 6002, roles: ['entity'], entityId: 6002 }
const HELD_ENTITY = { id: 7005, roles: ['entity'], entityId: 7005 }
const SYSTEM_ADMIN = { id: 2, roles: ['system_admin'] }
const FOLLOWER = { id: 3, roles: ['request_follower'] }
const ASSESSOR = { id: 10, roles: ['assessor'] }
const CRITERIA_OFFICER = { id: 11, roles: ['criteria_officer'] }
const SUPER_ADMIN = { id: 1, roles: [], superAdmin: true }

const RESOURCE_KEYS = [
  'award_cycle_id',
  'completed_at',
  'created_at',
  'criteria_update_in_progress',
  'criteria_version_id',
  'entity',
  'id',
  'is_frozen',
  'is_open',
  'number',
  'path',
  'result',
  'scale_kind',
  'status',
  'submitted_at',
  'updated_at',
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

function unitsOf(criteria) {
  return criteria.mains.flatMap((main) =>
    main.subs.flatMap((sub) =>
      sub.evaluation_type === 'guiding_points'
        ? sub.points.map((point) => ({ group: 'points', id: point.id, code: point.code }))
        : [{ group: 'subs', id: sub.id, code: sub.code }],
    ),
  )
}

function completeSheet(criteria, value = '8') {
  const sheet = { subs: {}, points: {} }
  unitsOf(criteria).forEach((unit) => {
    sheet[unit.group][unit.id] = value
  })
  return sheet
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

describe('self assessment mock — reads', () => {
  it('lists the own assessments with the exact resource keys and pagination meta', async () => {
    seedMockSession(OWNER)
    const { status, body } = await call('GET', '/assessments?filter[path]=self&sort=-created_at')
    expect(status).toBe(200)
    expect(body.data.map((item) => item.id)).toEqual([9002, 9001])
    expect(Object.keys(body.data[0]).sort()).toEqual(RESOURCE_KEYS)
    expect(body.data[1]).toMatchObject({
      number: 'SLF-2026-000001',
      path: 'self',
      status: 'result_available',
      is_open: false,
      is_frozen: false,
      criteria_update_in_progress: false,
      scale_kind: 'of_10',
      entity: {
        id: 5001,
        reference: 'ENT-005001',
        name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
        status: 'active',
      },
    })
    expect(Object.keys(body.data[1].result).sort()).toEqual(['max_total', 'percent', 'total'])
    expect(body.data[0].result).toBeNull()
    expect(body.meta.pagination).toEqual({ page: 1, per_page: 15, total: 2, last_page: 1 })
    expect(body.errors).toBeNull()
  })

  it('filters by status with the canonical value and rejects an unknown path, sort or filter', async () => {
    seedMockSession(OWNER)
    const drafts = await call('GET', '/assessments?filter[status]=draft')
    expect(drafts.body.data.map((item) => item.id)).toEqual([9002])
    const results = await call('GET', '/assessments?filter[status]=result_available')
    expect(results.body.data.map((item) => item.id)).toEqual([9001])
    const accredited = await call(
      'GET',
      '/assessments?filter[path]=accredited&filter[status]=completed',
    )
    expect(accredited.body.data.map((item) => item.id)).toEqual([9102])
    expect((await call('GET', '/assessments?filter[path]=kit')).status).toBe(422)
    expect((await call('GET', '/assessments?sort=number')).status).toBe(422)
    const unknown = await call('GET', '/assessments?filter[owner]=1')
    expect(unknown.body).toMatchObject({ code: 'validation_failed', data: null })
    expect(unknown.body.errors).toHaveProperty('filter')
  })

  it('lets global readers list every assessment, hides frozen ones unless the entity is named', async () => {
    seedMockSession(FOLLOWER)
    const all = await call('GET', '/assessments?filter[path]=self')
    expect(all.body.data.map((item) => item.id)).not.toContain(9004)
    expect(all.body.data.length).toBe(4)
    const frozen = await call('GET', '/assessments?filter[entity_id]=7002')
    expect(frozen.body.data).toHaveLength(1)
    expect(frozen.body.data[0]).toMatchObject({ id: 9004, is_frozen: true, is_open: true })
  })

  it('gives an assessor only the assigned requests, 404 on other records, and 403 without the permission', async () => {
    seedMockSession(ASSESSOR)
    expect((await call('GET', '/assessments')).body.data.map((item) => item.id).sort()).toEqual([
      9102, 9107, 9108, 9202, 9204,
    ])
    expect((await call('GET', '/assessments/9001')).status).toBe(404)
    seedMockSession(CRITERIA_OFFICER)
    expect((await call('GET', '/assessments')).status).toBe(403)
    expect((await call('GET', '/assessments/9001')).status).toBe(403)
    clearMockSession()
    expect((await call('GET', '/assessments')).status).toBe(401)
  })

  it('answers 404 for another entity and for an unknown id', async () => {
    seedMockSession(OTHER_ENTITY)
    expect((await call('GET', '/assessments/9001')).status).toBe(404)
    expect((await call('GET', '/assessments/9001/form')).status).toBe(404)
    expect((await call('GET', '/assessments/123456')).status).toBe(404)
  })

  it('serves the form with the bound tree, the scale, the raw values and the server progress', async () => {
    seedMockSession(OWNER)
    const { body } = await call('GET', '/assessments/9002/form')
    expect(Object.keys(body.data).sort()).toEqual([
      'assessment',
      'criteria',
      'criteria_update',
      'progress',
      'scale',
      'scores',
    ])
    expect(body.data.scale).toEqual({ kind: 'of_10', maximum: '10.00' })
    expect(body.data.criteria).toMatchObject({
      version_id: 1,
      version_number: 1,
      status: 'published',
      scale_kind: 'of_10',
    })
    const units = unitsOf(body.data.criteria)
    expect(body.data.progress).toEqual({ scored: 6, required: units.length })
    const firstPoint = units[0]
    expect(body.data.scores.points[firstPoint.id]).toEqual({ raw_value: '8.50', percent: '85.00' })
    expect(Object.keys(body.data.scores).sort()).toEqual(['points', 'subs'])
  })

  it('returns the Q-94 placeholder before the result and the full breakdown after it', async () => {
    seedMockSession(OWNER)
    const pending = await call('GET', '/assessments/9002/result')
    expect(pending.body.data).toEqual({
      id: 9002,
      number: 'SLF-2026-000002',
      path: 'self',
      status: 'draft',
      is_frozen: false,
      available: false,
    })
    const { body } = await call('GET', '/assessments/9001/result')
    expect(body.data).toMatchObject({
      available: true,
      result_version: 1,
      scale_kind: 'of_10',
      max_total: '8200.00',
    })
    expect(Object.keys(body.data.main[0]).sort()).toEqual([
      'code',
      'criterion_id',
      'earned',
      'name',
      'percent',
      'weight',
    ])
    expect(Object.keys(body.data.sub[0]).sort()).toEqual([
      'code',
      'earned',
      'evaluation_type',
      'main_criterion_id',
      'name',
      'percent',
      'raw_value',
      'sub_id',
      'weight',
    ])
    expect(Object.keys(body.data.points[0]).sort()).toEqual([
      'code',
      'description',
      'earned',
      'percent',
      'point_id',
      'raw_value',
      'sub_criterion_id',
      'weight',
    ])
    expect(body.data.percent).toMatch(/^\d+\.\d{2}$/)
  })
})

describe('self assessment mock — status history and criteria update', () => {
  it('returns status_history oldest first, without actor_staff for the owner and with it for staff readers', async () => {
    seedMockSession(OWNER)
    const own = await call('GET', '/assessments/9001')
    expect(own.body.data.status_history).toEqual([
      { from: null, to: 'draft', verb: 'start', at: '2026-06-20T07:00:00Z', actor: 'entity' },
      {
        from: 'draft',
        to: 'submitted',
        verb: 'submit',
        at: '2026-06-20T07:42:00Z',
        actor: 'entity',
      },
      {
        from: 'submitted',
        to: 'result_available',
        verb: 'compute_result',
        at: '2026-06-20T07:42:00Z',
        actor: 'system',
      },
    ])
    seedMockSession(FOLLOWER)
    const staff = await call('GET', '/assessments/9001')
    expect(staff.body.data.status_history.map((change) => change.actor_staff)).toEqual([
      null,
      null,
      null,
    ])
    const list = await call('GET', '/assessments?filter[entity_id]=5001')
    expect(list.body.data[0]).not.toHaveProperty('status_history')
  })

  it('names a staff actor with roles for staff readers and hides the Super Admin', async () => {
    seedMockSession(OWNER)
    const form = await call('GET', '/assessments/9002/form')
    expect(form.body.data.criteria_update).toBeNull()
    seedMockSession(SUPER_ADMIN)
    const submitted = await call('POST', '/assessments/9002/submit', {
      scores: completeSheet(form.body.data.criteria),
      reason: 'Submitted on behalf of the entity after a call.',
    })
    expect(submitted.status).toBe(200)
    const record = await call('GET', '/assessments/9002')
    expect(record.body.data.status_history.at(-2)).toMatchObject({
      verb: 'submit',
      actor: 'staff',
      actor_staff: { name: null, roles: [] },
    })
    seedMockSession(OWNER)
    const own = await call('GET', '/assessments/9002')
    expect(own.body.data.status_history.at(-2)).toEqual({
      from: 'draft',
      to: 'submitted',
      verb: 'submit',
      at: expect.any(String),
      actor: 'staff',
    })
    expect(own.body.data.status_history.at(-1)).toMatchObject({
      verb: 'compute_result',
      actor: 'system',
      to: 'result_available',
    })
  })
})

describe('self assessment mock — start, save and submit', () => {
  it('starts a draft with 201 and a sequential number, then refuses a second draft with its id', async () => {
    seedMockSession(OWNER)
    const first = await call('POST', '/assessments', { path: 'self' })
    expect(first.status).toBe(409)
    expect(first.body).toMatchObject({ code: 'self.draft_exists', meta: { assessment_id: 9002 } })
    seedMockSession(OTHER_ENTITY)
    const started = await call('POST', '/assessments', { path: 'self' })
    expect(started.status).toBe(201)
    expect(started.body.data).toMatchObject({
      number: 'SLF-2026-000006',
      status: 'draft',
      is_open: true,
      result: null,
    })
    expect((await call('POST', '/assessments', { path: 'accredited' })).status).toBe(422)
  })

  it('refuses staff, the Super Admin without an entity and a frozen entity', async () => {
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('POST', '/assessments', { path: 'self' })).status).toBe(403)
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', '/assessments', { path: 'self' })).body.code).toBe(
      'assessment.entity_required',
    )
    seedMockSession({ id: 7002, roles: ['entity'], entityId: 7002 })
    expect((await call('POST', '/assessments', { path: 'self' })).body.code).toBe('entity.deleted')
  })

  it('saves the complete sheet, normalizes raw values and clears omitted items', async () => {
    seedMockSession(OWNER)
    const form = await call('GET', '/assessments/9002/form')
    const [first, second] = unitsOf(form.body.data.criteria)
    const saved = await call('PUT', '/assessments/9002', {
      scores: { subs: {}, points: { [first.id]: '7.5', [second.id]: null } },
    })
    expect(saved.status).toBe(200)
    expect(Object.keys(saved.body.data).sort()).toEqual([
      'assessment',
      'progress',
      'scale',
      'scores',
    ])
    expect(saved.body.data.scores.points).toEqual({
      [first.id]: { raw_value: '7.50', percent: '75.00' },
    })
    expect(saved.body.data.progress.scored).toBe(1)
  })

  it('answers field errors for range, decimals, unknown items and direct scores on guiding-point subs', async () => {
    seedMockSession(OWNER)
    const form = await call('GET', '/assessments/9002/form')
    const point = unitsOf(form.body.data.criteria)[0]
    const guidingSub = form.body.data.criteria.mains[0].subs[0]
    const range = await call('PUT', '/assessments/9002', {
      scores: { points: { [point.id]: '11' } },
    })
    expect(range.status).toBe(422)
    expect(range.body.errors).toHaveProperty(`scores.points.${point.id}`)
    const decimals = await call('PUT', '/assessments/9002', {
      scores: { points: { [point.id]: '7.555' } },
    })
    expect(decimals.body.code).toBe('validation_failed')
    const unknown = await call('PUT', '/assessments/9002', { scores: { points: { 999999: '5' } } })
    expect(unknown.body.code).toBe('score.item_not_in_version')
    const direct = await call('PUT', '/assessments/9002', {
      scores: { subs: { [guidingSub.id]: '5' } },
    })
    expect(direct.body.code).toBe('score.use_points')
    expect(direct.body.errors).toHaveProperty(`scores.subs.${guidingSub.id}`)
    expect((await call('PUT', '/assessments/9002', {})).status).toBe(422)
  })

  it('refuses an incomplete submit without saving it and lists the missing items', async () => {
    seedMockSession(OWNER)
    const form = await call('GET', '/assessments/9002/form')
    const units = unitsOf(form.body.data.criteria)
    const { status, body } = await call('POST', '/assessments/9002/submit', {})
    expect(status).toBe(422)
    expect(body.code).toBe('scores.incomplete')
    expect(body.meta.missing).toHaveLength(units.length - 6)
    expect(body.meta.missing[0]).toEqual({
      level: units[6].group === 'subs' ? 'sub' : 'point',
      id: units[6].id,
      code: units[6].code,
    })
    expect(body.errors[`scores.${units[6].group}.${units[6].id}`][0]).toContain(units[6].code)
  })

  it('submits with the last scores and answers with the immutable result, then 409 on a second submit', async () => {
    seedMockSession(OWNER)
    const form = await call('GET', '/assessments/9002/form')
    const submitted = await call('POST', '/assessments/9002/submit', {
      scores: completeSheet(form.body.data.criteria),
    })
    expect(submitted.status).toBe(200)
    expect(submitted.body.data).toMatchObject({
      id: 9002,
      status: 'result_available',
      available: true,
      total: '6560.00',
      percent: '80.00',
    })
    const again = await call('POST', '/assessments/9002/submit', {})
    expect(again.status).toBe(409)
    expect(again.body).toMatchObject({
      code: 'state.transition_illegal',
      errors: { state: ['result_available'], attempted: ['submit'] },
    })
    expect((await call('PUT', '/assessments/9002', { scores: {} })).body.errors.attempted).toEqual([
      'save_draft',
    ])
  })

  it('refuses writes by read-only staff (403) and by other entities (404), and asks the Super Admin for a reason', async () => {
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('PUT', '/assessments/9002', { scores: {} })).status).toBe(403)
    seedMockSession(OTHER_ENTITY)
    expect((await call('PUT', '/assessments/9002', { scores: {} })).status).toBe(404)
    seedMockSession(SUPER_ADMIN)
    const missing = await call('PUT', '/assessments/9002', { scores: {} })
    expect(missing.body.code).toBe('reason_required')
    const withReason = await call('PUT', '/assessments/9002', {
      scores: {},
      reason: 'Correction requested by the entity in writing.',
    })
    expect(withReason.status).toBe(200)
  })

  it('freezes the draft of a deleted entity for everyone', async () => {
    seedMockSession(SUPER_ADMIN)
    const frozen = await call('PUT', '/assessments/9004', {
      scores: {},
      reason: 'Correction requested by the entity in writing.',
    })
    expect(frozen.status).toBe(409)
    expect(frozen.body.code).toBe('entity.deleted')
    const record = await call('GET', '/assessments/9004')
    expect(record.body.data.is_frozen).toBe(true)
  })

  it('blocks submission while the criteria update hold is set and keeps saving allowed', async () => {
    seedMockSession(HELD_ENTITY)
    const form = await call('GET', '/assessments/9005/form')
    expect(form.body.data.assessment.criteria_update_in_progress).toBe(true)
    const submit = await call('POST', '/assessments/9005/submit', {
      scores: completeSheet(form.body.data.criteria),
    })
    expect(submit.status).toBe(409)
    expect(submit.body.code).toBe('assessment.criteria_update_in_progress')
    const ar = await call('POST', '/assessments/9005/submit', {}, 'ar')
    expect(ar.body.message).toContain('يجري تحديث معايير التقييم')
    expect((await call('PUT', '/assessments/9005', { scores: {} })).status).toBe(200)
  })
})

describe('self assessment mock — criteria edits, restore and search', () => {
  async function publishNewVersion(scope) {
    seedMockSession(CRITERIA_OFFICER)
    const draft = await call('POST', '/criteria/versions', {})
    const versionId = draft.body.data.id
    const published = await call('POST', `/criteria/versions/${versionId}/publish`, {
      confirm: true,
      scope,
    })
    return { versionId, published }
  }

  it('re-binds open drafts on a reset_open publication, counts them and leaves frozen drafts alone', async () => {
    const { versionId, published } = await publishNewVersion('reset_open')
    expect(published.status).toBe(200)
    expect(published.body.meta.affected_open_count).toBe(7)
    seedMockSession(OWNER)
    const form = await call('GET', '/assessments/9002/form')
    expect(form.body.data.assessment.criteria_version_id).toBe(versionId)
    expect(form.body.data.progress.scored).toBe(0)
    expect(form.body.data.criteria_update).toMatchObject({
      from_version_number: 1,
      to_version_number: 2,
      scope: 'reset_open',
      trigger: 'criteria_edit',
      kept: [],
    })
    expect(form.body.data.criteria_update.reset).toHaveLength(6)
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('GET', '/assessments/9004')).body.data.criteria_version_id).toBe(1)
    expect((await call('GET', '/assessments/9001')).body.data.criteria_version_id).toBe(1)
  })

  it('keeps the scores of unchanged items on an all publication', async () => {
    const { versionId } = await publishNewVersion('all')
    seedMockSession(OWNER)
    const form = await call('GET', '/assessments/9002/form')
    expect(form.body.data.assessment.criteria_version_id).toBe(versionId)
    expect(form.body.data.progress.scored).toBe(6)
    const update = form.body.data.criteria_update
    expect(Object.keys(update).sort()).toEqual([
      'added',
      'from_version_id',
      'from_version_number',
      'kept',
      'moved_at',
      'reset',
      'scope',
      'to_version_id',
      'to_version_number',
      'trigger',
    ])
    expect(update).toMatchObject({
      from_version_id: 1,
      to_version_id: versionId,
      scope: 'all',
      trigger: 'criteria_edit',
      reset: [],
      added: [],
    })
    expect(update.kept).toHaveLength(6)
    expect(Object.keys(update.kept[0]).sort()).toEqual(['code', 'label', 'level'])
    expect(update.kept[0].label).toEqual({ ar: expect.any(String), en: expect.any(String) })
  })

  it('holds the drafts of a restored entity, re-binds them in the queued job and releases the hold', async () => {
    await publishNewVersion('reset_open')
    seedMockSession(SYSTEM_ADMIN)
    expect(
      (
        await call('POST', '/entities/7002/restore', {
          reason: 'Restored after the merger was cancelled.',
        })
      ).status,
    ).toBe(200)
    const held = await call('GET', '/assessments/9004')
    expect(held.body.data).toMatchObject({
      is_frozen: false,
      criteria_update_in_progress: true,
      criteria_version_id: 1,
    })
    runQueuedJobs()
    const settled = await call('GET', '/assessments/9004/form')
    expect(settled.body.data.assessment.criteria_update_in_progress).toBe(false)
    expect(settled.body.data.assessment.criteria_version_id).not.toBe(1)
    expect(settled.body.data.progress.scored).toBe(0)
    expect(settled.body.data.criteria_update).toMatchObject({
      trigger: 'entity_restore',
      scope: 'reset_open',
    })
  })

  it('serves the requests search group with the backend item shape, never frozen and only to global readers', async () => {
    seedMockSession(FOLLOWER)
    const { body } = await call('GET', '/search?q=SLF-2026')
    const requests = body.data.find((group) => group.type === 'requests')
    expect(requests.items[0]).toEqual({
      id: 9002,
      number: 'SLF-2026-000002',
      path: 'self',
      status: 'draft',
      is_frozen: false,
      entity: {
        id: 5001,
        reference: 'ENT-005001',
        name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
        status: 'active',
      },
    })
    expect(requests.items.map((item) => item.id)).toEqual([9002, 9005, 9003, 9001])
    expect(requests.items.map((item) => item.id)).not.toContain(9004)
    const byName = await call('GET', '/search?q=احسان')
    expect(
      byName.body.data.find((group) => group.type === 'requests').items.map((item) => item.id),
    ).toEqual([9108, 9202, 9103, 9003])
    seedMockSession(ASSESSOR)
    const assessor = await call('GET', '/search?q=SLF-2026')
    expect(assessor.body.data.find((group) => group.type === 'requests').items).toEqual([])
  })
})
