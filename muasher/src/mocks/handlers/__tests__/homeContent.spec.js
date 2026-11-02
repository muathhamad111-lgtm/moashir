import { describe, expect, it, beforeAll, afterAll, afterEach } from 'vitest'
import { setupServer } from 'msw/node'
import { contentHandlers } from '@/mocks/handlers/content'
import { modelCriteriaHandlers } from '@/mocks/handlers/modelCriteria'
import { MOCK_API_BASE, mockFetch } from '@/test-utils/mockApi'

const server = setupServer(...contentHandlers, ...modelCriteriaHandlers)
const BASE = MOCK_API_BASE

async function call(method, path, body) {
  const init = { method }
  if (body !== undefined) {
    init.headers = { 'content-type': 'application/json' }
    init.body = JSON.stringify(body)
  }
  const res = await mockFetch(`${BASE}${path}`, init)
  return { ok: res.ok, status: res.status, body: await res.json() }
}

async function retry(method, path, body, tries = 15) {
  for (let i = 0; i < tries; i += 1) {
    const r = await call(method, path, body)
    if (r.ok) return r
  }
  throw new Error(`step failed: ${method} ${path}`)
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

describe('home content mock — model integrity', () => {
  it('public home returns enabled sections ordered ascending, bilingual', async () => {
    const { body } = await retry('GET', '/content/home')
    const sections = body.data.sections
    expect(sections.length).toBeGreaterThan(0)
    expect(sections.every((section) => section.enabled)).toBe(true)
    for (let i = 1; i < sections.length; i += 1) {
      expect(sections[i].order).toBeGreaterThanOrEqual(sections[i - 1].order)
    }
    const hero = sections.find((section) => section.key === 'hero')
    expect(hero.title.ar).toBeTruthy()
    expect(hero.title.en).toBeTruthy()
    expect(body.data.siteSettings.contact.email).toBeTruthy()
  })

  it('admin home returns ALL sections including disabled', async () => {
    const admin = await retry('GET', '/admin/content/home')
    const total = admin.body.data.sections.length
    const target = admin.body.data.sections[0].id
    await retry('PATCH', `/admin/content/sections/${target}/enabled`, { enabled: false })

    const pub = await retry('GET', '/content/home')
    expect(pub.body.data.sections.find((section) => section.id === target)).toBeUndefined()

    const adminAfter = await retry('GET', '/admin/content/home')
    expect(adminAfter.body.data.sections.length).toBe(total)
    expect(adminAfter.body.data.sections.find((section) => section.id === target).enabled).toBe(
      false,
    )
    await retry('PATCH', `/admin/content/sections/${target}/enabled`, { enabled: true })
  }, 20000)

  it('updateSection persists editable fields but not id/key', async () => {
    const { body } = await retry('PATCH', '/admin/content/sections/sec-hero', {
      id: 'HACK',
      key: 'HACK',
      title: { ar: 'عنوان جديد', en: 'New title' },
      ctaUrl: '/go',
    })
    expect(body.data.id).toBe('sec-hero')
    expect(body.data.key).toBe('hero')
    expect(body.data.title.ar).toBe('عنوان جديد')
    expect(body.data.ctaUrl).toBe('/go')
  })

  it('reorders sections by ordered id list', async () => {
    const admin = await retry('GET', '/admin/content/home')
    const ids = admin.body.data.sections.map((section) => section.id)
    const swapped = [ids[1], ids[0], ...ids.slice(2)]
    const { body } = await retry('PATCH', '/admin/content/sections/reorder', {
      orderedIds: swapped,
    })
    expect(body.data[0].id).toBe(swapped[0])
    expect(body.data[1].id).toBe(swapped[1])
    await retry('PATCH', '/admin/content/sections/reorder', { orderedIds: ids })
  }, 20000)

  it('supports item CRUD within a section (add, update, reorder, remove)', async () => {
    const created = await retry('POST', '/admin/content/sections/sec-partners/items', {
      title: { ar: 'شريك جديد', en: 'New partner' },
      image: '/logo.png',
    })
    const partners = created.body.data.items
    const added = partners[partners.length - 1]
    expect(added.title.en).toBe('New partner')
    expect(added.id).toBeTruthy()

    const updated = await retry('POST', '/admin/content/sections/sec-partners/items', {
      id: added.id,
      title: { ar: 'شريك محدث', en: 'Updated partner' },
    })
    expect(
      updated.body.data.items.find((entry) => entry.id === added.id).title.en,
    ).toBe('Updated partner')

    const ordered = updated.body.data.items.map((entry) => entry.id)
    const reordered = [ordered[ordered.length - 1], ...ordered.slice(0, -1)]
    const afterReorder = await retry(
      'PATCH',
      '/admin/content/sections/sec-partners/items/reorder',
      { orderedIds: reordered },
    )
    expect(afterReorder.body.data.items[0].id).toBe(added.id)

    const afterRemove = await retry(
      'DELETE',
      `/admin/content/sections/sec-partners/items/${added.id}`,
    )
    expect(afterRemove.body.data.items.find((entry) => entry.id === added.id)).toBeUndefined()
  }, 20000)

  it('reads and updates site settings', async () => {
    const before = await retry('GET', '/admin/content/site-settings')
    expect(Array.isArray(before.body.data.social)).toBe(true)
    const { body } = await retry('PATCH', '/admin/content/site-settings', {
      footerNote: { ar: 'حقوق محفوظة', en: 'Rights reserved' },
    })
    expect(body.data.footerNote.en).toBe('Rights reserved')
  })

  it('admin item edit + site-settings edit reflect on the public home (shared store)', async () => {
    const created = await retry('POST', '/admin/content/sections/sec-evaluation-paths/items', {
      title: { ar: 'مسار منشور', en: 'Published path' },
      description: { ar: 'وصف', en: 'Desc' },
    })
    const items = created.body.data.items
    const addedId = items[items.length - 1].id

    await retry('PATCH', '/admin/content/site-settings', {
      footerNote: { ar: 'تذييل منشور', en: 'Published footer' },
    })

    const pub = await retry('GET', '/content/home')
    const paths = pub.body.data.sections.find((section) => section.key === 'evaluation-paths')
    expect(paths.items.find((entry) => entry.id === addedId).title.en).toBe('Published path')
    expect(pub.body.data.siteSettings.footerNote.en).toBe('Published footer')

    await retry('DELETE', `/admin/content/sections/sec-evaluation-paths/items/${addedId}`)
  }, 20000)

  it('exposes the accredited-intro + advanced-intro CMS sections with descriptive copy + items (Notes 8b/8c)', async () => {
    const admin = await retry('GET', '/admin/content/home')
    const sections = admin.body.data.sections

    const accredited = sections.find((section) => section.key === 'accredited-intro')
    expect(accredited).toBeTruthy()
    expect(accredited.title.ar).toBeTruthy()
    expect(accredited.title.en).toBeTruthy()
    expect(accredited.meta.whyRecommended.body.ar).toBeTruthy()
    expect(accredited.meta.fees.body.en).toBeTruthy()
    expect(accredited.meta.payment.body.en).toBeTruthy()
    expect(accredited.items.length).toBeGreaterThan(0)
    expect(accredited.items[0].title.ar).toBeTruthy()

    const advanced = sections.find((section) => section.key === 'advanced-intro')
    expect(advanced).toBeTruthy()
    expect(advanced.meta.award.body.ar).toBeTruthy()
    expect(advanced.meta.nextCycle.date).toBeTruthy()
    const groups = new Set(advanced.items.map((entry) => entry.meta.group))
    expect(groups.has('winner')).toBe(true)
    expect(groups.has('partner')).toBe(true)
    expect(groups.has('sponsor')).toBe(true)
    expect(groups.has('supporter')).toBe(true)
  })

  it('lets an admin edit the accredited-intro copy (CMS-managed intro)', async () => {
    const { body } = await retry('PATCH', '/admin/content/sections/sec-accredited-intro', {
      subtitle: { ar: 'وصف محدث', en: 'Updated subtitle' },
    })
    expect(body.data.key).toBe('accredited-intro')
    expect(body.data.subtitle.en).toBe('Updated subtitle')
  })

  it('legacy landing shim still returns hero + path cards for Home.vue', async () => {
    const { body } = await retry('GET', '/content/landing')
    expect(body.data.hero.headline.ar).toBeTruthy()
    expect(body.data.pathCards.length).toBe(3)
    expect(body.data.sections.bag).toBeTruthy()
  })

  it('legacy model-criteria shim returns nine ordered criteria', async () => {
    const { body } = await retry('GET', '/content/model-criteria')
    expect(body.data.length).toBe(9)
    expect(body.data[0].order).toBe(1)
  })
})
