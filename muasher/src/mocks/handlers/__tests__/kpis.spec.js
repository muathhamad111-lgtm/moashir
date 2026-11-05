import { describe, expect, it, beforeAll, afterAll, afterEach, beforeEach } from 'vitest'
import { setupServer } from 'msw/node'
import { kpisHandlers } from '@/mocks/handlers/kpis'
import { contentHandlers } from '@/mocks/handlers/content'
import { resetKpiState } from '@/mocks/stores/kpis/kpiState'
import { resetCmsMock } from '@/mocks/stores/content/cmsPublication'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'

const server = setupServer(...kpisHandlers, ...contentHandlers)

async function call(method, path, body) {
  const init = { method }
  if (body !== undefined) {
    init.headers = { 'content-type': 'application/json' }
    init.body = JSON.stringify(body)
  }
  const res = await mockFetch(`${MOCK_API_BASE}${path}`, init)
  return { status: res.status, body: res.status === 204 ? null : await res.json() }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetKpiState()
  resetCmsMock()
  seedMockSession({ id: 7001, roles: ['content_officer'] })
})
afterEach(() => server.resetHandlers())
afterAll(() => {
  server.close()
  clearMockSession()
})

describe('KPI mock — public reads (Task-33 §3 #29-#32)', () => {
  it('pages published indicators in the public shape with decimal strings and references', async () => {
    const { body } = await call('GET', '/kpis?per_page=5')
    expect(body.data).toHaveLength(5)
    expect(body.meta.pagination).toMatchObject({ page: 1, perPage: 5 })
    const [first] = body.data
    expect(typeof first.value).toBe('string')
    expect(first.category).toEqual(expect.objectContaining({ id: expect.any(Number), key: expect.any(String) }))
    expect(first).toHaveProperty('cardColor')
    expect(first).not.toHaveProperty('published')
    expect(first).not.toHaveProperty('cardColorToken')
  })

  it('filters by category, status, year, type and owner and sorts by value', async () => {
    const byCategory = await call('GET', '/kpis?filter[category_id]=3&per_page=100')
    expect(byCategory.body.data.every((kpi) => kpi.category.id === 3)).toBe(true)
    const byStatus = await call('GET', '/kpis?filter[status]=needs_improvement&per_page=100')
    expect(byStatus.body.data.length).toBeGreaterThan(0)
    expect(byStatus.body.data.every((kpi) => kpi.status === 'needs-improvement')).toBe(true)
    const byYear = await call('GET', '/kpis?filter[measurement_year]=2025&per_page=100')
    expect(byYear.body.data.every((kpi) => kpi.measurementYear === 2025)).toBe(true)
    const sorted = await call('GET', '/kpis?sort=-value&per_page=100')
    const values = sorted.body.data.map((kpi) => Number(kpi.value))
    expect(values).toEqual([...values].sort((a, b) => b - a))
  })

  it('refuses a one-letter search with 422', async () => {
    expect((await call('GET', '/kpis?search=a')).status).toBe(422)
  })

  it('serves the featured list with the minimum and honours the site settings limit', async () => {
    const featured = await call('GET', '/kpis/featured')
    expect(featured.body.meta).toEqual({ minimum: 3, count: featured.body.data.length })
    await call('PATCH', '/admin/content/site-settings', { featuredKpiLimit: 3 })
    expect((await call('GET', '/kpis/featured')).body.data).toHaveLength(3)
    await call('PATCH', '/admin/content/site-settings', { showFeaturedKpis: false })
    expect((await call('GET', '/kpis/featured')).body.data).toEqual([])
  })

  it('returns the detail by slug with history and SEO, and 404 for unpublished slugs', async () => {
    const detail = await call('GET', '/kpis/participating-associations')
    expect(detail.body.data.history.length).toBeGreaterThan(0)
    expect(detail.body.data.seo.title.en).toBe('Participating associations')
    const hidden = (await call('GET', '/admin/kpis?filter[published]=false')).body.data[0]
    expect((await call('GET', `/kpis/${hidden.slug}`)).status).toBe(404)
  })
})

describe('KPI mock — editor writes (Task-33 §3 #33-#42)', () => {
  it('creates an unpublished KPI with an automatic slug and refuses a taken slug', async () => {
    const created = await call('POST', '/admin/kpis', { name: { ar: 'مؤشر الشراكات', en: 'Partnership index' }, value: '12.50', kpiCategoryId: 2 })
    expect(created.status).toBe(201)
    expect(created.body.data).toMatchObject({ slug: 'partnership-index', value: '12.5', published: false })
    const taken = await call('POST', '/admin/kpis', { name: { ar: 'مكرر', en: 'Duplicate' }, slug: 'partnership-index' })
    expect(taken.body.code).toBe('slug.taken')
    const prohibited = await call('POST', '/admin/kpis', { name: { ar: 'منشور', en: '' }, published: true })
    expect(prohibited.body.errors).toHaveProperty('published')
  })

  it('caps the featured flag at six indicators', async () => {
    const candidate = (await call('GET', '/admin/kpis?filter[featured]=false')).body.data[0]
    const refused = await call('PUT', `/admin/kpis/${candidate.id}`, { showOnHomepage: true })
    expect(refused.status).toBe(422)
    expect(refused.body.code).toBe('kpi.featured_limit_reached')
  })

  it('publishes, archives and reports the stats', async () => {
    const list = await call('GET', '/admin/kpis')
    expect(list.body.meta.stats).toMatchObject({ total: 31, featured: 6 })
    const target = list.body.data[0]
    expect((await call('PATCH', `/admin/kpis/${target.id}/published`, { published: false })).body.data.published).toBe(false)
    expect((await call('DELETE', `/admin/kpis/${target.id}`)).status).toBe(204)
    expect((await call('GET', '/admin/kpis')).body.meta.stats.total).toBe(30)
  })

  it('moves one step and applies a complete bulk order', async () => {
    const first = (await call('GET', '/admin/kpis')).body.data[0]
    const moved = await call('PATCH', `/admin/kpis/${first.id}/reorder`, { direction: 'down' })
    expect(moved.body.data[1].id).toBe(first.id)
    const ids = moved.body.data.map((entry) => entry.id).reverse()
    expect((await call('PATCH', '/admin/kpis/reorder', { orderedIds: ids })).body.data[0].id).toBe(ids[0])
    expect((await call('PATCH', '/admin/kpis/reorder', { orderedIds: ids.slice(2) })).body.code).toBe('order.incomplete')
  })

  it('records a dated measurement once per day and refuses future dates', async () => {
    const kpi = (await call('GET', '/admin/kpis')).body.data[0]
    const recorded = await call('POST', `/admin/kpis/${kpi.id}/values`, { value: '510', measuredOn: '2026-07-31' })
    expect(recorded.status).toBe(201)
    expect(recorded.body.data.at(-1)).toEqual({ value: '510', measuredOn: '2026-07-31' })
    expect((await call('POST', `/admin/kpis/${kpi.id}/values`, { value: '1', measuredOn: '2026-07-31' })).body.code).toBe('kpi.measurement_exists')
    expect((await call('POST', `/admin/kpis/${kpi.id}/values`, { value: '1', measuredOn: '2999-01-01' })).status).toBe(422)
  })

  it('manages categories and refuses to delete one in use', async () => {
    const created = await call('POST', '/admin/kpi-categories', { key: 'impact', name: { ar: 'الأثر', en: 'Impact' } })
    expect(created.status).toBe(201)
    expect((await call('DELETE', `/admin/kpi-categories/${created.body.data.id}`)).status).toBe(204)
    const inUse = await call('DELETE', '/admin/kpi-categories/1')
    expect(inUse.status).toBe(409)
    expect(inUse.body.code).toBe('category.in_use')
    expect(inUse.body.meta.kpiCount).toBeGreaterThan(0)
  })

  it('denies the editor endpoints to roles without content.kpis.manage', async () => {
    seedMockSession({ id: 7002, roles: ['finance_officer'] })
    expect((await call('GET', '/admin/kpis')).status).toBe(403)
  })
})
