import { describe, expect, it, beforeAll, afterAll, afterEach } from 'vitest'
import { setupServer } from 'msw/node'
import { kpisHandlers } from '@/mocks/handlers/kpis'
import { MOCK_API_BASE, mockFetch } from '@/test-utils/mockApi'

const server = setupServer(...kpisHandlers)
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

async function retry(method, path, body, tries = 20) {
  let last
  for (let i = 0; i < tries; i += 1) {
    last = await call(method, path, body)
    if (last.ok) return last
  }
  throw new Error(`step failed: ${method} ${path} (status ${last?.status})`)
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

describe('kpis mock — expanded model (chunk 3A)', () => {
  it('public list returns published, ordered indicators carrying the new fields', async () => {
    const { body } = await retry('GET', '/kpis')
    const items = body.data
    expect(items.length).toBeGreaterThan(0)
    expect(items.every((kpi) => kpi.published)).toBe(true)
    for (let i = 1; i < items.length; i += 1) {
      expect(items[i].order).toBeGreaterThanOrEqual(items[i - 1].order)
    }
    const first = items[0]
    expect(first.slug).toBeTruthy()
    expect(first.category).toBeTruthy()
    expect(first.status).toBeTruthy()
    expect(first.owner.ar).toBeTruthy()
    expect(first.dataSource.en).toBeTruthy()
    expect(typeof first.showOnHomepage).toBe('boolean')
  })

  it('exposes a homepage subset via showOnHomepage', async () => {
    const { body } = await retry('GET', '/kpis')
    const featured = body.data.filter((kpi) => kpi.showOnHomepage)
    expect(featured.length).toBeGreaterThanOrEqual(3)
    expect(featured.length).toBeLessThanOrEqual(8)
  })

  it('public list with homepage=true returns only featured, capped at six', async () => {
    const { body } = await retry('GET', '/kpis?homepage=true')
    const items = body.data
    expect(items.length).toBeGreaterThanOrEqual(3)
    expect(items.length).toBeLessThanOrEqual(6)
    expect(items.every((kpi) => kpi.published && kpi.showOnHomepage)).toBe(true)
  })

  it('getBySlug returns the matching PUBLISHED indicator with all fields', async () => {
    const { body } = await retry('GET', '/kpis/participating-associations')
    const kpi = body.data
    expect(kpi.slug).toBe('participating-associations')
    expect(kpi.published).toBe(true)
    expect(kpi.baseline).not.toBeUndefined()
    expect(kpi.lastUpdate).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(kpi.calcMethod.ar).toBeTruthy()
    expect(kpi.badgeColor).toBeTruthy()
    expect(kpi.cardColor).toBeTruthy()
  })

  it('getBySlug 404s for an unknown or unpublished slug', async () => {
    let res
    for (let i = 0; i < 20; i += 1) {
      res = await call('GET', '/kpis/does-not-exist')
      if (res.status === 404) break
    }
    expect(res.status).toBe(404)
  })

  it('creates an indicator that persists the new fields and gets a unique slug', async () => {
    const payload = {
      name: { ar: 'مؤشر تجريبي', en: 'Sample new indicator' },
      description: { ar: 'وصف', en: 'Description' },
      value: 120,
      target: 200,
      baseline: 80,
      unit: { ar: 'وحدة', en: 'units' },
      category: 'innovation',
      status: 'good',
      owner: { ar: 'إدارة الابتكار', en: 'Innovation Department' },
      dataSource: { ar: 'سجل الابتكار', en: 'Innovation registry' },
      lastUpdate: '2025-04-01',
      externalLink: 'https://example.sa',
      calcMethod: { ar: 'طريقة', en: 'Method' },
      notes: { ar: 'ملاحظة', en: 'Note' },
      icon: 'fa-solid fa-rocket',
      color: 'info',
      badgeColor: 'warning',
      cardColor: 'success',
      showOnHomepage: true,
      published: true,
    }
    const { body } = await retry('POST', '/admin/kpis', payload)
    const created = body.data
    expect(created.id).toBeTruthy()
    expect(created.slug).toBe('sample-new-indicator')
    expect(created.baseline).toBe(80)
    expect(created.category).toBe('innovation')
    expect(created.status).toBe('good')
    expect(created.owner).toEqual(payload.owner)
    expect(created.dataSource).toEqual(payload.dataSource)
    expect(created.lastUpdate).toBe('2025-04-01')
    expect(created.externalLink).toBe('https://example.sa')
    expect(created.calcMethod).toEqual(payload.calcMethod)
    expect(created.notes).toEqual(payload.notes)
    expect(created.badgeColor).toBe('warning')
    expect(created.cardColor).toBe('success')
    expect(created.showOnHomepage).toBe(true)

    const bySlug = await retry('GET', `/kpis/${created.slug}`)
    expect(bySlug.body.data.id).toBe(created.id)
  }, 20000)

  it('updates an indicator and round-trips the new fields', async () => {
    const admin = await retry('GET', '/admin/kpis?per_page=50')
    const target = admin.body.data[0]
    const { body } = await retry('PUT', `/admin/kpis/${target.id}`, {
      category: 'operations',
      status: 'behind-target',
      baseline: 5,
      owner: { ar: 'إدارة محدثة', en: 'Updated department' },
      badgeColor: 'danger',
      cardColor: 'warning',
      showOnHomepage: true,
    })
    const updated = body.data
    expect(updated.category).toBe('operations')
    expect(updated.status).toBe('behind-target')
    expect(updated.baseline).toBe(5)
    expect(updated.owner.en).toBe('Updated department')
    expect(updated.badgeColor).toBe('danger')
    expect(updated.cardColor).toBe('warning')
    expect(updated.showOnHomepage).toBe(true)
    expect(updated.name).toEqual(target.name)
  }, 20000)
})
