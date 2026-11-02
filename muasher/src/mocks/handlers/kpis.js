import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { kpisFixture } from '../fixtures/kpis'
import { uniqueSlug } from '@/utils/slug'
import { failsRandomly } from '../contract/faults'

let kpis = kpisFixture.map((item) => ({ ...item }))

let idSeed = 900000

function nextId() {
  idSeed += 1
  return idSeed
}

function slugFor(source, excludeId = null) {
  const taken = kpis.filter((kpi) => kpi.id !== excludeId).map((kpi) => kpi.slug).filter(Boolean)
  return uniqueSlug(source, taken)
}

function byOrder(a, b) {
  return a.order - b.order
}

function normalizeOrder() {
  kpis.sort(byOrder).forEach((kpi, index) => {
    kpi.order = index + 1
  })
}

function localized(content, locale = 'ar') {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}

const maybeFail = failsRandomly(0.1)

export const kpisHandlers = [
  http.get('/api/v1/kpis', async ({ request }) => {
    await delay(420)
    if (maybeFail()) {
      return errorResponse('Failed to load performance indicators', 503)
    }
    const url = new URL(request.url)
    const homepageOnly = url.searchParams.get('homepage') === 'true'
    let published = kpis.filter((kpi) => kpi.published).sort(byOrder)
    if (homepageOnly) {
      published = published.filter((kpi) => kpi.showOnHomepage).slice(0, 6)
    }
    return HttpResponse.json({
      data: published,
      meta: {
        pagination: { page: 1, perPage: published.length, total: published.length, lastPage: 1 },
      },
      errors: null,
    })
  }),

  http.get('/api/v1/kpis/:slug', async ({ params }) => {
    await delay(300)
    if (maybeFail(0.06)) return errorResponse('Failed to load the indicator', 503)
    const kpi = kpis.find((item) => item.published && item.slug === params.slug)
    if (!kpi) return errorResponse('Indicator not found', 404)
    return envelope({ ...kpi })
  }),

  http.get('/api/v1/admin/kpis', async ({ request }) => {
    await delay(420)
    if (maybeFail(0.08)) return errorResponse('Failed to load performance indicators', 503)
    const url = new URL(request.url)
    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase()
    const publishedFilter = url.searchParams.get('filter[published]')
    const page = Number(url.searchParams.get('page')) || 1
    const perPage = Number(url.searchParams.get('perPage')) || 12

    let items = kpis.map((kpi) => ({ ...kpi }))
    if (search) {
      items = items.filter(
        (kpi) =>
          localized(kpi.name, 'ar').toLowerCase().includes(search) ||
          localized(kpi.name, 'en').toLowerCase().includes(search),
      )
    }
    if (publishedFilter === 'true') items = items.filter((kpi) => kpi.published)
    else if (publishedFilter === 'false') items = items.filter((kpi) => !kpi.published)

    items.sort(byOrder)

    const total = items.length
    const lastPage = Math.max(1, Math.ceil(total / perPage))
    const start = (page - 1) * perPage
    const pageItems = items.slice(start, start + perPage)

    return envelope(pageItems, {
      pagination: { page, perPage: perPage, total, lastPage: lastPage },
      stats: {
        total: kpis.length,
        published: kpis.filter((kpi) => kpi.published).length,
        unpublished: kpis.filter((kpi) => !kpi.published).length,
      },
    })
  }),

  http.get('/api/v1/admin/kpis/:kpiId', async ({ params }) => {
    await delay(300)
    if (maybeFail(0.06)) return errorResponse('Failed to load the indicator', 503)
    const kpi = kpis.find((item) => item.id === Number(params.kpiId))
    if (!kpi) return errorResponse('Indicator not found', 404)
    return envelope({ ...kpi })
  }),

  http.post('/api/v1/admin/kpis', async ({ request }) => {
    await delay(440)
    if (maybeFail()) return errorResponse('Could not create the indicator', 500)
    const body = await request.json()
    const maxOrder = kpis.reduce((max, kpi) => Math.max(max, kpi.order), 0)
    const created = {
      id: nextId(),
      name: body.name,
      slug: slugFor(body.slug || body.name?.en || body.name?.ar),
      value: Number(body.value) || 0,
      target: body.target === '' || body.target === null ? null : Number(body.target),
      baseline: body.baseline === '' || body.baseline === null || body.baseline === undefined ? null : Number(body.baseline),
      unit: body.unit,
      description: body.description,
      category: body.category || null,
      status: body.status || null,
      owner: body.owner ?? { ar: '', en: '' },
      dataSource: body.dataSource ?? { ar: '', en: '' },
      lastUpdate: body.lastUpdate || null,
      externalLink: body.externalLink || '',
      calcMethod: body.calcMethod ?? { ar: '', en: '' },
      notes: body.notes ?? { ar: '', en: '' },
      icon: body.icon || 'fa-solid fa-chart-simple',
      color: body.color || 'primary',
      badgeColor: body.badgeColor || body.color || 'primary',
      cardColor: body.cardColor || body.color || 'primary',
      showOnHomepage: Boolean(body.showOnHomepage),
      order: body.order ? Number(body.order) : maxOrder + 1,
      published: Boolean(body.published),
    }
    kpis.push(created)
    normalizeOrder()
    return envelope({ ...created })
  }),

  http.put('/api/v1/admin/kpis/:kpiId', async ({ request, params }) => {
    await delay(400)
    if (maybeFail()) return errorResponse('Could not update the indicator', 500)
    const kpi = kpis.find((item) => item.id === Number(params.kpiId))
    if (!kpi) return errorResponse('Indicator not found', 404)
    const body = await request.json()
    Object.assign(kpi, {
      name: body.name ?? kpi.name,
      slug: kpi.slug || slugFor(body.name?.en || body.name?.ar, kpi.id),
      value: body.value === undefined ? kpi.value : Number(body.value) || 0,
      target:
        body.target === undefined
          ? kpi.target
          : body.target === '' || body.target === null
            ? null
            : Number(body.target),
      baseline:
        body.baseline === undefined
          ? kpi.baseline
          : body.baseline === '' || body.baseline === null
            ? null
            : Number(body.baseline),
      unit: body.unit ?? kpi.unit,
      description: body.description ?? kpi.description,
      category: body.category === undefined ? kpi.category : body.category,
      status: body.status === undefined ? kpi.status : body.status,
      owner: body.owner ?? kpi.owner,
      dataSource: body.dataSource ?? kpi.dataSource,
      lastUpdate: body.lastUpdate === undefined ? kpi.lastUpdate : body.lastUpdate || null,
      externalLink: body.externalLink === undefined ? kpi.externalLink : body.externalLink,
      calcMethod: body.calcMethod ?? kpi.calcMethod,
      notes: body.notes ?? kpi.notes,
      icon: body.icon ?? kpi.icon,
      color: body.color ?? kpi.color,
      badgeColor: body.badgeColor ?? kpi.badgeColor,
      cardColor: body.cardColor ?? kpi.cardColor,
      showOnHomepage:
        body.showOnHomepage === undefined ? kpi.showOnHomepage : Boolean(body.showOnHomepage),
      order: body.order === undefined ? kpi.order : Number(body.order) || kpi.order,
      published: body.published === undefined ? kpi.published : Boolean(body.published),
    })
    normalizeOrder()
    return envelope({ ...kpi })
  }),

  http.delete('/api/v1/admin/kpis/:kpiId', async ({ params }) => {
    await delay(360)
    if (maybeFail(0.08)) return errorResponse('Could not delete the indicator', 500)
    const index = kpis.findIndex((item) => item.id === Number(params.kpiId))
    if (index === -1) return errorResponse('Indicator not found', 404)
    kpis.splice(index, 1)
    normalizeOrder()
    return envelope({ id: Number(params.kpiId) })
  }),

  http.patch('/api/v1/admin/kpis/:kpiId/published', async ({ request, params }) => {
    await delay(280)
    if (maybeFail(0.08)) return errorResponse('Could not change the publish state', 500)
    const kpi = kpis.find((item) => item.id === Number(params.kpiId))
    if (!kpi) return errorResponse('Indicator not found', 404)
    const body = await request.json()
    kpi.published = Boolean(body.published)
    return envelope({ ...kpi })
  }),

  http.patch('/api/v1/admin/kpis/:kpiId/reorder', async ({ request, params }) => {
    await delay(260)
    if (maybeFail(0.08)) return errorResponse('Could not reorder the indicator', 500)
    const body = await request.json()
    const ordered = [...kpis].sort(byOrder)
    const index = ordered.findIndex((item) => item.id === Number(params.kpiId))
    if (index === -1) return errorResponse('Indicator not found', 404)
    const swapWith = body.direction === 'up' ? index - 1 : index + 1
    if (swapWith < 0 || swapWith >= ordered.length) {
      return envelope(ordered.map((item) => ({ ...item })))
    }
    const current = ordered[index]
    const neighbour = ordered[swapWith]
    const currentOrder = current.order
    current.order = neighbour.order
    neighbour.order = currentOrder
    normalizeOrder()
    return envelope(kpis.sort(byOrder).map((item) => ({ ...item })))
  }),
]
