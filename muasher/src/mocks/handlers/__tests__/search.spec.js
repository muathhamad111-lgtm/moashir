import { describe, expect, it, beforeAll, afterAll, afterEach, beforeEach } from 'vitest'
import { setupServer } from 'msw/node'
import { searchHandlers } from '@/mocks/handlers/search'
import { normalizeSearchText } from '@/mocks/stores/searchIndex'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'
import { pinMockClock, releaseMockClock } from '@/test-utils/mockClock'

const server = setupServer(...searchHandlers)
const BASE = MOCK_API_BASE

function signInAs(roles, id = 7001) {
  seedMockSession({ id, roles })
}

async function search(q) {
  const res = await mockFetch(`${BASE}/search?q=${encodeURIComponent(q)}`)
  return { status: res.status, body: await res.json() }
}

function types(body) {
  return body.data.map((group) => group.type)
}

function groupOf(body, type) {
  return body.data.find((group) => group.type === type)
}

beforeAll(() => {
  pinMockClock()
  resetAssessmentsStore()
  server.listen({ onUnhandledRequest: 'error' })
})
beforeEach(() => clearMockSession())
afterEach(() => server.resetHandlers())
afterAll(() => {
  server.close()
  clearMockSession()
  releaseMockClock()
})

describe('global search mock (GET /search)', () => {
  it('returns the envelope with the groups as an array for a follower (entities + requests)', async () => {
    signInAs(['request_follower'])
    const { status, body } = await search('إحسان')
    expect(status).toBe(200)
    expect(body.errors).toBeNull()
    expect(types(body)).toEqual(['entities', 'requests'])
    expect(groupOf(body, 'entities').items[0]).toEqual({
      id: 6002,
      reference: 'ENT-006002',
      name: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
      status: 'active',
    })
    expect(groupOf(body, 'requests').items[0]).toEqual({
      id: 9108,
      number: 'ACC-2026-000008',
      path: 'accredited',
      pathLabel: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
      status: 'under-review',
      isFrozen: false,
      entity: {
        id: 6002,
        reference: 'ENT-006002',
        name: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
        status: 'active',
      },
    })
    expect(body.meta).toEqual({ limit: 5 })
  })

  it('returns every visible group to the System Administrator, empty ones included, in the fixed order', async () => {
    signInAs(['system_admin'])
    const { body } = await search('القحطاني')
    expect(types(body)).toEqual(['entities', 'requests', 'users'])
    expect(groupOf(body, 'entities')).toEqual({ type: 'entities', total: 0, items: [] })
    const [user] = groupOf(body, 'users').items
    expect(Object.keys(user).sort()).toEqual(['email', 'id', 'name', 'roles', 'status'])
    expect(user.roles).toEqual(['system_admin'])
  })

  it('gives the finance officer the entities group only and caps items with limit', async () => {
    signInAs(['finance_officer'])
    const res = await mockFetch(`${BASE}/search?q=${encodeURIComponent('جمعية')}&limit=1`)
    const body = await res.json()
    expect(types(body)).toEqual(['entities'])
    expect(body.data[0].items).toHaveLength(1)
    expect(body.data[0].total).toBeGreaterThan(1)
    const bad = await mockFetch(`${BASE}/search?q=${encodeURIComponent('جمعية')}&limit=11`)
    expect(bad.status).toBe(422)
  })

  it('gives an assessor the requests group without results until assignments exist', async () => {
    signInAs(['assessor'], 10)
    const { body } = await search('SLF-2026')
    expect(groupOf(body, 'requests')).toEqual({ type: 'requests', total: 0, items: [] })
  })

  it('finds requests by number and never lists frozen requests', async () => {
    signInAs(['request_follower'])
    const { body } = await search('slf-2026-000004')
    expect(groupOf(body, 'requests').items).toEqual([])
    const byNumber = await search('SLF-2026-000001')
    expect(groupOf(byNumber.body, 'requests').items.map((item) => item.number)).toEqual([
      'SLF-2026-000001',
    ])
  })

  it('answers 403 to a role without any searchable provider', async () => {
    signInAs(['content_officer'])
    const denied = await search('جمعية')
    expect(denied.status).toBe(403)
    expect(denied.body.code).toBe('forbidden')
    signInAs(['entity'])
    expect((await search('جمعية')).status).toBe(403)
  })

  it('validates the length of q and requires a session', async () => {
    signInAs(['request_follower'])
    const short = await search('ج')
    expect(short.status).toBe(422)
    expect((await search('ـًـ')).status).toBe(422)
    expect(short.body.code).toBe('validation_failed')
    expect(short.body.errors.q).toBeTruthy()
    const long = await search('ج'.repeat(101))
    expect(long.status).toBe(422)
    clearMockSession()
    const anonymous = await search('جمعية')
    expect(anonymous.status).toBe(401)
  })

  it('normalizes Arabic letter variants, diacritics and tatweel', async () => {
    expect(normalizeSearchText('إحْسـان')).toBe('احسان')
    expect(normalizeSearchText('مؤسسة رواد')).toBe('موسسه رواد')
    expect(normalizeSearchText('\u0669\u0661\u0660\u0661 \u06F2')).toBe('9101 2')
    signInAs(['request_follower'])
    const { body } = await search('احسان')
    expect(groupOf(body, 'entities').items[0].id).toBe(6002)
    const english = await search('EHSAN')
    expect(groupOf(english.body, 'entities').items[0].id).toBe(6002)
  })
})
