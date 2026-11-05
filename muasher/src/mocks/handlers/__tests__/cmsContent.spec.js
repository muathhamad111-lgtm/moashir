import { describe, expect, it, beforeAll, afterAll, afterEach, beforeEach } from 'vitest'
import { setupServer } from 'msw/node'
import { contentHandlers } from '@/mocks/handlers/content'
import { resetCmsMock } from '@/mocks/stores/content/cmsPublication'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'

const server = setupServer(...contentHandlers)

async function call(method, path, body) {
  const init = { method }
  if (body !== undefined) {
    init.headers = { 'content-type': 'application/json' }
    init.body = JSON.stringify(body)
  }
  const res = await mockFetch(`${MOCK_API_BASE}${path}`, init)
  return { status: res.status, body: res.status === 204 ? null : await res.json() }
}

async function homeSections() {
  return (await call('GET', '/admin/content/pages/home')).body.data.sections
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCmsMock()
  seedMockSession({ id: 7001, roles: ['content_officer'] })
})
afterEach(() => server.resetHandlers())
afterAll(() => {
  server.close()
  clearMockSession()
})

describe('CMS mock — public reads (Task-33 §3 #1-#5)', () => {
  it('serves the published home with server-side fallback and no editor fields', async () => {
    const { body } = await call('GET', '/content/home')
    expect(body.data.published).toBe(true)
    expect(body.data.maintenance).toBe(false)
    const hero = body.data.sections.find((section) => section.type === 'hero')
    expect(hero.title.ar).toBeTruthy()
    expect(hero.title.en).toBeTruthy()
    expect(hero).not.toHaveProperty('enabled')
    expect(hero).not.toHaveProperty('order')
    expect(hero.image.url).toMatch(/^\/api\/v1\/content\/files\/[0-9a-f-]{36}$/)
    expect(body.data.siteSettings).not.toHaveProperty('updatedAt')
    expect(body.data.siteSettings.featuredKpiLimit).toBe(6)
  })

  it('reports maintenance on /content/status and /content/home while the home is unpublished', async () => {
    expect((await call('POST', '/admin/content/pages/home/unpublish')).status).toBe(200)
    expect((await call('GET', '/content/status')).body.data).toEqual({ maintenance: true, publishedAt: null })
    expect((await call('GET', '/content/home')).body.data).toEqual({ published: false, maintenance: true })
  })

  it('serves the other published pages by key and 404s unknown or unpublished ones', async () => {
    const lenses = await call('GET', '/content/pages/lenses')
    expect(lenses.body.data.key).toBe('lenses')
    expect(lenses.body.data.sections.length).toBeGreaterThan(0)
    expect((await call('GET', '/content/pages/privacy')).body.code).toBe('not_found')
    await call('POST', '/admin/content/pages/lenses/unpublish')
    const hidden = await call('GET', '/content/pages/lenses')
    expect(hidden.status).toBe(404)
    expect(hidden.body.code).toBe('cms.page_not_published')
  })
})

describe('CMS mock — Content Officer writes (Task-33 §3 #6-#24)', () => {
  it('keeps edits in the draft until the page is published again', async () => {
    const [hero] = await homeSections()
    const patched = await call('PATCH', `/admin/content/sections/${hero.id}`, { title: { ar: 'عنوان جديد', en: '' } })
    expect(patched.body.data.title).toEqual({ ar: 'عنوان جديد', en: '' })
    const summary = (await call('GET', '/admin/content/pages')).body.data.find((page) => page.key === 'home')
    expect(summary.hasUnpublishedChanges).toBe(true)
    expect((await call('GET', '/content/home')).body.data.sections[0].title.ar).not.toBe('عنوان جديد')
    const published = await call('POST', '/admin/content/pages/home/publish')
    expect(published.body.data.hasUnpublishedChanges).toBe(false)
    expect((await call('GET', '/content/home')).body.data.sections[0].title).toEqual({ ar: 'عنوان جديد', en: 'عنوان جديد' })
  })

  it('creates a section, refuses a duplicate key and deletes it', async () => {
    const created = await call('POST', '/admin/content/pages/home/sections', { key: 'faq_block', type: 'faq', title: { ar: 'أسئلة', en: 'Questions' } })
    expect(created.status).toBe(201)
    expect(created.body.data.page).toBe('home')
    const duplicate = await call('POST', '/admin/content/pages/home/sections', { key: 'faq_block', type: 'faq' })
    expect(duplicate.status).toBe(422)
    expect(duplicate.body.code).toBe('cms.section_key_taken')
    expect((await call('DELETE', `/admin/content/sections/${created.body.data.id}`)).status).toBe(204)
  })

  it('rejects locked fields and unsafe links with field errors', async () => {
    const [hero] = await homeSections()
    const locked = await call('PATCH', `/admin/content/sections/${hero.id}`, { key: 'other' })
    expect(locked.status).toBe(422)
    expect(locked.body.errors).toHaveProperty('key')
    const unsafe = await call('PATCH', `/admin/content/sections/${hero.id}`, { ctaUrl: 'javascript:alert(1)' })
    expect(unsafe.body.errors).toHaveProperty('ctaUrl')
  })

  it('reorders sections only with a complete permutation', async () => {
    const ids = (await homeSections()).map((section) => section.id)
    const incomplete = await call('PATCH', '/admin/content/sections/reorder', { orderedIds: ids.slice(1) })
    expect(incomplete.body.code).toBe('order.incomplete')
    const reversed = await call('PATCH', '/admin/content/sections/reorder', { orderedIds: [...ids].reverse() })
    expect(reversed.body.data.map((section) => section.id)).toEqual([...ids].reverse())
  })

  it('creates with POST, updates with PUT, toggles, reorders and removes items', async () => {
    const section = (await homeSections()).find((entry) => entry.type === 'how_it_works')
    const created = await call('POST', `/admin/content/sections/${section.id}/items`, { title: { ar: 'خطوة', en: 'Step' } })
    const item = created.body.data.items.at(-1)
    expect(created.status).toBe(201)
    const updated = await call('PUT', `/admin/content/sections/${section.id}/items/${item.id}`, { icon: 'fa-solid fa-star' })
    expect(updated.body.data.items.find((entry) => entry.id === item.id).icon).toBe('fa-solid fa-star')
    const toggled = await call('PATCH', `/admin/content/sections/${section.id}/items/${item.id}/enabled`, { enabled: false })
    expect(toggled.body.data.items.find((entry) => entry.id === item.id).enabled).toBe(false)
    const order = toggled.body.data.items.map((entry) => entry.id).reverse()
    const reordered = await call('PATCH', `/admin/content/sections/${section.id}/items/reorder`, { orderedIds: order })
    expect(reordered.body.data.items.map((entry) => entry.id)).toEqual(order)
    expect((await call('DELETE', `/admin/content/sections/${section.id}/items/${item.id}`)).status).toBe(204)
  })

  it('refuses to publish a page without an enabled section', async () => {
    const kpisPage = (await call('GET', '/admin/content/pages/kpis')).body.data
    await call('PATCH', `/admin/content/sections/${kpisPage.sections[0].id}/enabled`, { enabled: false })
    const refused = await call('POST', '/admin/content/pages/kpis/publish')
    expect(refused.status).toBe(422)
    expect(refused.body.code).toBe('cms.page_empty')
  })

  it('patches site settings and refuses unknown keys and out-of-range limits', async () => {
    const saved = await call('PATCH', '/admin/content/site-settings', { featuredKpiLimit: 4, showFeaturedKpis: false })
    expect(saved.body.data.featuredKpiLimit).toBe(4)
    const refused = await call('PATCH', '/admin/content/site-settings', { featuredKpiLimit: 9, colour: 'red' })
    expect(refused.status).toBe(422)
    expect(Object.keys(refused.body.errors).sort()).toEqual(['colour', 'featuredKpiLimit'])
  })

  it('denies the CMS to a role without content.home.manage', async () => {
    seedMockSession({ id: 7002, roles: ['finance_officer'] })
    expect((await call('GET', '/admin/content/pages')).status).toBe(403)
    clearMockSession()
    expect((await call('GET', '/admin/content/pages')).status).toBe(401)
  })
})
