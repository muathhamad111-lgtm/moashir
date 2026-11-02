import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { notificationAuditFixture } from '../fixtures/notifications'
import { notificationsStore as store, visibleTo } from '../stores/notificationsStore'
import { currentSessionUser } from '../stores/sessionStore'
import { RESTRICTED_DETAIL_HIDDEN_ROLES } from '@/constants/notifications'

function withoutActor(item) {
  const { actorId: _actorId, ...visible } = item
  return visible
}

function paginate(items, page, perPage) {
  const start = (page - 1) * perPage
  return {
    data: items.slice(start, start + perPage).map(withoutActor),
    meta: {
      pagination: {
        page,
        perPage: perPage,
        total: items.length,
        lastPage: Math.max(1, Math.ceil(items.length / perPage)),
      },
    },
    errors: null,
  }
}

function scopeForRole(items, role) {
  const visible = items.filter(visibleTo(currentSessionUser()))
  if (!role) return visible
  return visible.filter((item) => {
    if (item.recipientRole !== role) return false
    if (item.restrictedDetail && RESTRICTED_DETAIL_HIDDEN_ROLES.includes(role)) return false
    return true
  })
}

function matchesSearch(item, term, wantRestricted) {
  const haystack = [
    item.code,
    item.title?.ar,
    item.title?.en,
    wantRestricted ? item.body?.ar : '',
    wantRestricted ? item.body?.en : '',
  ]
  return haystack.some((value) => (value ?? '').toLowerCase().includes(term))
}

export const notificationsHandlers = [
  http.get('/api/v1/notifications', async ({ request }) => {
    await delay(350)
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const perPage = Number(url.searchParams.get('perPage') ?? 15)
    const typeFilter = url.searchParams.get('filter[type]')
    const readFilter = url.searchParams.get('filter[read]')
    const roleFilter = url.searchParams.get('filter[recipientRole]')
    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase()

    let items = scopeForRole([...store], roleFilter)
    items = items.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
    if (typeFilter) items = items.filter((item) => item.type === typeFilter)
    if (readFilter !== null && readFilter !== '') {
      const wantRead = readFilter === 'true' || readFilter === '1'
      items = items.filter((item) => item.read === wantRead)
    }
    if (search) {
      const canSeeRestricted = !RESTRICTED_DETAIL_HIDDEN_ROLES.includes(roleFilter ?? '')
      items = items.filter((item) => matchesSearch(item, search, canSeeRestricted))
    }
    return HttpResponse.json(paginate(items, page, perPage))
  }),

  http.get('/api/v1/notifications/unread-count', async ({ request }) => {
    await delay(200)
    const url = new URL(request.url)
    const roleFilter = url.searchParams.get('filter[recipientRole]')
    const scoped = scopeForRole(store, roleFilter)
    const count = scoped.filter((item) => !item.read).length
    return HttpResponse.json({ data: { count }, meta: {}, errors: null })
  }),

  http.get('/api/v1/notifications/:id/audit', async ({ params }) => {
    await delay(300)
    const id = Number(params.id)
    const notification = store.find((candidate) => candidate.id === id)
    if (!notification) {
      return HttpResponse.json(
        { message: 'Notification not found', errors: {}, code: 404 },
        { status: 404 },
      )
    }
    const entries = notificationAuditFixture[id] ?? []
    return HttpResponse.json({
      data: { code: notification.code, link: notification.link, entries },
      meta: {},
      errors: null,
    })
  }),

  http.patch('/api/v1/notifications/:id/read', async ({ params }) => {
    await delay(200)
    const item = store.find((candidate) => candidate.id === Number(params.id))
    if (!item) {
      return HttpResponse.json(
        { message: 'Notification not found', errors: {}, code: 404 },
        { status: 404 },
      )
    }
    item.read = true
    return HttpResponse.json({ data: item, meta: {}, errors: null })
  }),

  http.post('/api/v1/notifications/read-all', async ({ request }) => {
    await delay(250)
    const payload = await request.json().catch(() => null)
    const roleFilter = payload?.recipientRole ?? null
    let updated = 0
    const scoped = scopeForRole(store, roleFilter)
    for (const item of scoped) {
      if (!item.read) {
        item.read = true
        updated += 1
      }
    }
    return HttpResponse.json({ data: { updated }, meta: {}, errors: null })
  }),
]
