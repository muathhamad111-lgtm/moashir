import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { criteriaHandlers } from '@/mocks/handlers/criteria'
import { assessmentsHandlers } from '@/mocks/handlers/assessments'
import { contentHandlers } from '@/mocks/handlers/content'
import { notificationsHandlers } from '@/mocks/handlers/notifications'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import {
  MOCK_API_BASE,
  clearMockSession,
  mockFetch,
  seedMockSession,
  wireFetch,
} from '@/test-utils/mockApi'

const server = setupServer(
  ...criteriaHandlers,
  ...assessmentsHandlers,
  ...contentHandlers,
  ...notificationsHandlers,
)

const OFFICER = { id: 7101, roles: ['criteria_officer'] }
const SYSTEM_ADMIN = { id: 7102, roles: ['system_admin'] }

async function call(method, path, body, headers = {}) {
  const init = { method, headers: { 'accept-language': 'en', ...headers } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await mockFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

function as(descriptor) {
  seedMockSession(descriptor)
}

async function openDraft() {
  as(OFFICER)
  const created = await call('POST', '/criteria/versions', {})
  expect(created.status).toBe(201)
  return created.body.data
}

async function draftMains() {
  return (await call('GET', '/criteria/mains')).body.data
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => resetCriteriaStore())
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('criteria mock — authorization mirrors the backend abilities', () => {
  it('answers 401 without a session and 403 without criteria.view', async () => {
    expect((await call('GET', '/criteria/mains')).status).toBe(401)
    as({ id: 7103, roles: ['finance_officer'] })
    const denied = await call('GET', '/criteria/versions')
    expect(denied.status).toBe(403)
    expect(denied.body.code).toBe('forbidden')
  })

  it('lets the System Administrator read and publish but never edit the tree', async () => {
    as(SYSTEM_ADMIN)
    expect((await call('GET', '/criteria/mains')).status).toBe(200)
    expect((await call('POST', '/criteria/versions', {})).status).toBe(403)
    expect((await call('PUT', '/criteria/mains/1', { weight: '10' })).status).toBe(403)
  })
})

describe('criteria mock — versions and the published seed', () => {
  it('serves the published version 1 seeded from the backend content', async () => {
    as(OFFICER)
    const list = await call('GET', '/criteria/mains')
    expect(list.body.meta.version).toMatchObject({ versionNumber: 1, status: 'published' })
    expect(list.body.data).toHaveLength(9)
    expect(list.body.data[0]).toMatchObject({ code: 'M1', weight: '1000.00', weightBalanced: true })
    const versions = await call('GET', '/criteria/versions')
    expect(versions.body.data.map((version) => version.status)).toEqual(['published'])
  })

  it('refuses tree edits without a draft and on a published version', async () => {
    as(OFFICER)
    const noDraft = await call('POST', '/criteria/mains', {
      name: { ar: 'معيار', en: 'Criterion' },
      weight: '10',
    })
    expect(noDraft).toMatchObject({ status: 409, body: { code: 'criteria.no_draft' } })
    const frozen = await call('PATCH', '/criteria/mains/1/active', { active: false })
    expect(frozen).toMatchObject({ status: 409, body: { code: 'criteria.version_not_draft' } })
  })

  it('clones the published version into a single draft that keeps the codes', async () => {
    const draft = await openDraft()
    expect(draft).toMatchObject({ status: 'draft', versionNumber: 2, clonedFromVersionId: 1 })
    expect((await call('POST', '/criteria/versions', {})).body.code).toBe('criteria.draft_exists')
    const mains = await draftMains()
    expect(mains[0]).toMatchObject({ code: 'M1', versionId: draft.id })
    expect(mains[0].id).not.toBe(1)
  })

  it('discards a draft but never a published version', async () => {
    const draft = await openDraft()
    expect((await call('DELETE', `/criteria/versions/${draft.id}`)).status).toBe(204)
    expect((await call('DELETE', '/criteria/versions/1')).body.code).toBe(
      'criteria.version_not_draft',
    )
  })
})

describe('criteria mock — weight guards', () => {
  it('blocks a sub-criterion above the remaining main weight with the remaining weight in meta', async () => {
    await openDraft()
    const [main] = await draftMains()
    const rejected = await call('POST', '/criteria/subs', {
      mainCriterionId: main.id,
      name: { ar: 'معيار فرعي', en: 'Sub-criterion' },
      weight: '50.00',
    })
    expect(rejected.status).toBe(422)
    expect(rejected.body).toMatchObject({
      code: 'criteria.weight_exceeds_remaining',
      meta: { remainingWeight: '0.00' },
    })
    expect(rejected.body.errors.weight[0]).toContain('0.00')
  })

  it('refuses to lower a main weight below its allocated sub-criteria', async () => {
    await openDraft()
    const [main] = await draftMains()
    const rejected = await call('PUT', `/criteria/mains/${main.id}`, { weight: '100.00' })
    expect(rejected.body).toMatchObject({
      code: 'criteria.weight_below_allocated',
      meta: { allocatedWeight: '1000.00' },
    })
  })

  it('distributes equally in hundredths with the remainder on the first items', async () => {
    await openDraft()
    const [main] = await draftMains()
    await call('PUT', `/criteria/mains/${main.id}`, { distributeEqually: true, weight: '1000.00' })
    const detail = await call('GET', `/criteria/mains/${main.id}`)
    expect(detail.body.data.subs.map((sub) => sub.weight)).toEqual(['333.34', '333.33', '333.33'])
  })

  it('needs a confirmation with the point count before switching to direct evaluation', async () => {
    await openDraft()
    const [main] = await draftMains()
    const sub = (await call('GET', `/criteria/mains/${main.id}`)).body.data.subs[0]
    const warned = await call('PATCH', `/criteria/subs/${sub.id}/evaluation-type`, {
      evaluationType: 'direct',
    })
    expect(warned.body).toMatchObject({
      code: 'criteria.points_confirm_required',
      meta: { impact: { pointCount: 3 } },
    })
    const switched = await call('PATCH', `/criteria/subs/${sub.id}/evaluation-type`, {
      evaluationType: 'direct',
      confirmDeletePoints: true,
    })
    expect(switched.body.data).toMatchObject({ evaluationType: 'direct', pointCount: 0 })
  })

  it('rejects an incomplete reorder', async () => {
    await openDraft()
    const [first] = await draftMains()
    const rejected = await call('POST', '/criteria/mains/reorder', { orderedIds: [first.id] })
    expect(rejected.body.code).toBe('order.incomplete')
  })
})

describe('criteria mock — publish with scope and text corrections', () => {
  it('blocks an inconsistent publish with the offending nodes in errors and meta', async () => {
    const draft = await openDraft()
    const [main] = await draftMains()
    const sub = (await call('GET', `/criteria/mains/${main.id}`)).body.data.subs[2]
    await call('PATCH', `/criteria/subs/${sub.id}/active`, { active: false })
    as(SYSTEM_ADMIN)
    const rejected = await call('POST', `/criteria/versions/${draft.id}/publish`, {
      confirm: true,
      scope: 'all',
    })
    expect(rejected.status).toBe(422)
    expect(rejected.body.code).toBe('criteria.weights_inconsistent')
    expect(rejected.body.errors[`nodes.main.${main.id}`][0]).toContain(main.code)
    expect(rejected.body.meta.problems[0]).toMatchObject({
      nodeType: 'main',
      nodeId: main.id,
      problem: 'weights_mismatch',
      expectedWeight: '1000.00',
      actualWeight: '750.00',
    })
  })

  it('requires a scope when a published version is replaced and archives the previous one', async () => {
    const draft = await openDraft()
    as(SYSTEM_ADMIN)
    const missing = await call('POST', `/criteria/versions/${draft.id}/publish`, { confirm: true })
    expect(missing.body.code).toBe('criteria.scope_required')
    const published = await call('POST', `/criteria/versions/${draft.id}/publish`, {
      confirm: true,
      scope: 'reset_open',
    })
    expect(published.body.data).toMatchObject({ status: 'published', scaleKind: 'of-10' })
    expect(published.body.meta.effect).toMatchObject({
      scope: 'reset_open',
      clearsAllOpenScores: true,
      reachesClosedAssessments: false,
    })
    const archived = await call('GET', '/criteria/versions/1')
    expect(archived.body.data.status).toBe('archived')
  })

  it('corrects texts in place on published and archived versions only', async () => {
    as(OFFICER)
    const corrected = await call('PATCH', '/criteria/versions/1/labels', {
      nodes: [
        { type: 'main', id: 1, name: { ar: 'القيادة المؤسسية', en: 'Institutional Leadership' } },
      ],
    })
    expect(corrected.body.meta).toMatchObject({
      changeCount: 1,
      effect: { kind: 'text_correction', scope: 'all', reachesClosedAssessments: true },
    })
    expect((await call('GET', '/criteria/mains/1')).body.data.main.name.en).toBe(
      'Institutional Leadership',
    )
    const draft = await openDraft()
    expect(
      (
        await call('PATCH', `/criteria/versions/${draft.id}/labels`, {
          label: { ar: 'مسودة', en: 'Draft' },
        })
      ).body.code,
    ).toBe('criteria.version_is_draft')
    const foreign = await call('PATCH', '/criteria/versions/1/labels', {
      nodes: [{ type: 'main', id: 999, name: { ar: 'اسم', en: 'Name' } }],
    })
    expect(foreign.body.code).toBe('criteria.node_not_in_version')
  })
})

describe('criteria mock — STD notifications, published tree and public model', () => {
  it('notifies the other recipients but never the actor', async () => {
    await openDraft()
    await call('POST', '/criteria/mains', {
      name: { ar: 'الاستدامة', en: 'Sustainability' },
      weight: '100.00',
    })
    const actorView = await call(
      'GET',
      '/notifications?filter[recipientRole]=criteria_officer&perPage=100',
    )
    expect(
      actorView.body.data.some(
        (item) => item.code === 'STD-01' && item.body.en.includes('Sustainability'),
      ),
    ).toBe(false)
    as(SYSTEM_ADMIN)
    const adminView = await call(
      'GET',
      '/notifications?filter[recipientRole]=system_admin&perPage=100',
    )
    const notice = adminView.body.data.find(
      (item) => item.code === 'STD-01' && item.body.en.includes('Sustainability'),
    )
    expect(notice).toMatchObject({
      readAt: null,
      link: expect.stringMatching(/^\/admin\/settings\/criteria\/\d+$/),
    })
    expect(notice.actorId).toBeUndefined()
  })

  it('serves the published tree with canonical enum values on the wire', async () => {
    as({ id: 5001, roles: ['entity'], entityId: 5001 })
    const response = await wireFetch(`${MOCK_API_BASE}/assessments/criteria`)
    const body = await response.json()
    expect(body.data).toMatchObject({
      version_number: 1,
      status: 'published',
      scale_kind: 'of_10',
      total_weight: '8200.00',
    })
    expect(body.data.mains[0].subs[0]).toMatchObject({
      code: 'M1.1',
      evaluation_type: 'guiding_points',
      weight: '400.00',
    })
    expect((await wireFetch(`${MOCK_API_BASE}/assessments/criteria?version=1`)).status).toBe(403)
  })

  it('serves the public excellence model without weights', async () => {
    const { body } = await call('GET', '/content/model-criteria')
    expect(body.data).toHaveLength(9)
    expect(Object.keys(body.data[0]).sort()).toEqual([
      'code',
      'description',
      'group',
      'id',
      'name',
      'order',
    ])
    expect(new Set(body.data.map((main) => main.group))).toEqual(
      new Set(['enablers', 'creativity_learning', 'results']),
    )
  })
})
