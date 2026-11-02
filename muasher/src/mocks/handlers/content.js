import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { homeContentFixture, modelCriteriaFixture } from '../fixtures/content'
import { failsRandomly } from '../contract/faults'

const store = {
  published: homeContentFixture.published,
  sections: homeContentFixture.sections.map((section) => cloneSection(section)),
  siteSettings: cloneSettings(homeContentFixture.siteSettings),
}

let itemIdSeed = 700000

function nextItemId() {
  itemIdSeed += 1
  return `item-${itemIdSeed}`
}

function cloneItem(entry) {
  return JSON.parse(JSON.stringify(entry))
}

function cloneSection(section) {
  return JSON.parse(JSON.stringify(section))
}

function cloneSettings(settings) {
  return JSON.parse(JSON.stringify(settings))
}

function byOrder(a, b) {
  return a.order - b.order
}

function normalizeSectionOrder() {
  store.sections.sort(byOrder).forEach((section, index) => {
    section.order = index + 1
  })
}

function normalizeItemOrder(section) {
  section.items.sort(byOrder).forEach((entry, index) => {
    entry.order = index + 1
  })
}

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}

const maybeFail = failsRandomly(0.08)

function findSection(id) {
  return store.sections.find((section) => section.id === id)
}

function publicSections() {
  return store.sections.filter((section) => section.enabled).sort(byOrder).map(cloneSection)
}

function landingShim() {
  const hero = findSection('sec-hero')
  const paths = findSection('sec-evaluation-paths')
  const bag = findSection('sec-fast-excellence-kit')
  const lenses = findSection('sec-analytics-lenses')
  const leadership = findSection('sec-leadership')
  return {
    published: store.published,
    hero: {
      headline: hero ? hero.title : { ar: '', en: '' },
      subtitle: hero ? hero.subtitle : { ar: '', en: '' },
    },
    intro: {
      title: { ar: 'عن المنصة', en: 'About the platform' },
      body: hero ? hero.description : { ar: '', en: '' },
    },
    pathCards: (paths ? paths.items : []).slice().sort(byOrder).map((entry, index) => ({
      key: entry.id,
      icon: entry.icon,
      title: entry.title,
      summary: entry.description,
      order: index + 1,
    })),
    modelCriteria: modelCriteriaFixture,
    sections: {
      bag: bag
        ? { title: bag.title, body: bag.description, icon: bag.icon, future: false }
        : undefined,
      lenses: lenses
        ? { title: lenses.title, body: lenses.description, icon: lenses.icon, future: true }
        : undefined,
      leadership: leadership
        ? {
            title: leadership.title,
            body: leadership.description,
            icon: leadership.icon,
            future: true,
          }
        : undefined,
    },
  }
}

const MAINTENANCE_PAYLOAD = { published: false, maintenance: true }

function setHomePublished(published) {
  store.published = published
  return { key: 'home', published: store.published }
}

export const contentHandlers = [
  http.post('/api/v1/admin/content/pages/:key/publish', async ({ params }) => {
    await delay(260)
    if (params.key !== 'home') return errorResponse('Page not found', 404)
    return envelope(setHomePublished(true))
  }),

  http.post('/api/v1/admin/content/pages/:key/unpublish', async ({ params }) => {
    await delay(260)
    if (params.key !== 'home') return errorResponse('Page not found', 404)
    return envelope(setHomePublished(false))
  }),

  http.get('/api/v1/content/landing', async () => {
    await delay(380)
    if (maybeFail()) return errorResponse('Failed to load landing content', 503)
    if (!store.published) return envelope(MAINTENANCE_PAYLOAD)
    return envelope(landingShim())
  }),

  http.get('/api/v1/content/home', async () => {
    await delay(400)
    if (maybeFail()) return errorResponse('Failed to load the homepage content', 503)
    if (!store.published) return envelope(MAINTENANCE_PAYLOAD)
    return envelope({ sections: publicSections(), siteSettings: cloneSettings(store.siteSettings) })
  }),

  http.get('/api/v1/admin/content/home', async () => {
    await delay(400)
    if (maybeFail()) return errorResponse('Failed to load the homepage content', 503)
    return envelope(
      {
        published: store.published,
        sections: store.sections.slice().sort(byOrder).map(cloneSection),
        siteSettings: cloneSettings(store.siteSettings),
      },
      { total: store.sections.length },
    )
  }),

  http.patch('/api/v1/admin/content/sections/reorder', async ({ request }) => {
    await delay(280)
    if (maybeFail()) return errorResponse('Could not reorder the sections', 500)
    const body = await request.json()
    const orderedIds = Array.isArray(body.orderedIds) ? body.orderedIds : []
    orderedIds.forEach((id, index) => {
      const section = findSection(id)
      if (section) section.order = index + 1
    })
    normalizeSectionOrder()
    return envelope(store.sections.slice().sort(byOrder).map(cloneSection))
  }),

  http.patch('/api/v1/admin/content/sections/:sectionId/enabled', async ({ request, params }) => {
    await delay(240)
    if (maybeFail()) return errorResponse('Could not change the section state', 500)
    const section = findSection(params.sectionId)
    if (!section) return errorResponse('Section not found', 404)
    const body = await request.json()
    section.enabled = Boolean(body.enabled)
    return envelope(cloneSection(section))
  }),

  http.patch('/api/v1/admin/content/sections/:sectionId', async ({ request, params }) => {
    await delay(360)
    if (maybeFail()) return errorResponse('Could not update the section', 500)
    const section = findSection(params.sectionId)
    if (!section) return errorResponse('Section not found', 404)
    const patch = await request.json()
    const editable = { ...patch }
    delete editable.id
    delete editable.key
    delete editable.items
    delete editable.order
    Object.assign(section, editable)
    return envelope(cloneSection(section))
  }),

  http.post('/api/v1/admin/content/sections/:sectionId/items', async ({ request, params }) => {
    await delay(320)
    if (maybeFail()) return errorResponse('Could not save the item', 500)
    const section = findSection(params.sectionId)
    if (!section) return errorResponse('Section not found', 404)
    const body = await request.json()
    if (body.id) {
      const existing = section.items.find((entry) => entry.id === body.id)
      if (existing) {
        const editable = { ...body }
        delete editable.id
        delete editable.order
        Object.assign(existing, editable)
        normalizeItemOrder(section)
        return envelope(cloneSection(section))
      }
    }
    const maxOrder = section.items.reduce((max, entry) => Math.max(max, entry.order), 0)
    const created = cloneItem({
      icon: '',
      image: '',
      title: { ar: '', en: '' },
      description: { ar: '', en: '' },
      ctaLabel: { ar: '', en: '' },
      ctaUrl: '',
      meta: {},
      ...body,
      id: body.id || nextItemId(),
      order: body.order ? Number(body.order) : maxOrder + 1,
    })
    section.items.push(created)
    normalizeItemOrder(section)
    return envelope(cloneSection(section))
  }),

  http.delete(
    '/api/v1/admin/content/sections/:sectionId/items/:itemId',
    async ({ params }) => {
      await delay(300)
      if (maybeFail()) return errorResponse('Could not remove the item', 500)
      const section = findSection(params.sectionId)
      if (!section) return errorResponse('Section not found', 404)
      const index = section.items.findIndex((entry) => entry.id === params.itemId)
      if (index === -1) return errorResponse('Item not found', 404)
      section.items.splice(index, 1)
      normalizeItemOrder(section)
      return envelope(cloneSection(section))
    },
  ),

  http.patch(
    '/api/v1/admin/content/sections/:sectionId/items/reorder',
    async ({ request, params }) => {
      await delay(260)
      if (maybeFail()) return errorResponse('Could not reorder the items', 500)
      const section = findSection(params.sectionId)
      if (!section) return errorResponse('Section not found', 404)
      const body = await request.json()
      const orderedIds = Array.isArray(body.orderedIds) ? body.orderedIds : []
      orderedIds.forEach((id, index) => {
        const entry = section.items.find((candidate) => candidate.id === id)
        if (entry) entry.order = index + 1
      })
      normalizeItemOrder(section)
      return envelope(cloneSection(section))
    },
  ),

  http.get('/api/v1/admin/content/site-settings', async () => {
    await delay(280)
    if (maybeFail()) return errorResponse('Failed to load the site settings', 503)
    return envelope(cloneSettings(store.siteSettings))
  }),

  http.patch('/api/v1/admin/content/site-settings', async ({ request }) => {
    await delay(320)
    if (maybeFail()) return errorResponse('Could not update the site settings', 500)
    const patch = await request.json()
    store.siteSettings = { ...store.siteSettings, ...patch }
    return envelope(cloneSettings(store.siteSettings))
  }),
]
