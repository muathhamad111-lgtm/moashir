import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { MockRejection, rejectionResponse } from '../contract/rejection'
import { currentSessionUser } from '../stores/sessionStore'
import { findOwnNotification, inboxOf } from '../stores/notificationsStore'
import { preferencesOf, updatePreferences } from '../stores/notificationPreferencesStore'
import { NOTIFICATION_MOCK_MESSAGES } from '../fixtures/notificationMessages'

const LATENCY_MS = 250
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
const FLAGS = { true: true, false: false }

function viewer() {
  const user = currentSessionUser()
  if (!user) throw new MockRejection(401, 'unauthenticated')
  return user
}

function owned(user, id) {
  const item = UUID.test(id) ? findOwnNotification(user, id) : null
  if (!item) throw new MockRejection(404, 'not_found')
  return item
}

function resource({ recipientRole: _role, actorId: _actor, ...item }) {
  return {
    ...item,
    module: item.code.split('-')[0],
    pathLabel: null,
    isRead: item.readAt !== null,
    actionedAt: null,
  }
}

function localizedFields(fields) {
  return Object.fromEntries(
    Object.entries(fields).map(([field, issue]) => [
      field,
      [NOTIFICATION_MOCK_MESSAGES[issue.key]],
    ]),
  )
}

function route(resolve) {
  return async (info) => {
    await delay(LATENCY_MS)
    try {
      return await resolve(info)
    } catch (error) {
      if (!(error instanceof MockRejection)) throw error
      const localized = new MockRejection(error.status, error.code, {
        fields: localizedFields(error.fields),
      })
      return rejectionResponse(localized, info.request, NOTIFICATION_MOCK_MESSAGES)
    }
  }
}

function matchesSearch(item, term) {
  return [item.code, item.title?.ar, item.title?.en, item.body?.ar, item.body?.en].some((value) =>
    (value ?? '').toLowerCase().includes(term),
  )
}

function filtered(items, query) {
  const read = FLAGS[query.get('filter[read]')]
  const archived = FLAGS[query.get('filter[archived]')] === true
  const category = query.get('filter[category]')
  const code = query.get('filter[code]')
  const path = query.get('filter[path]')
  const term = (query.get('search') ?? '').trim().toLowerCase()
  return items.filter(
    (item) =>
      (item.archivedAt !== null) === archived &&
      (read === undefined || (item.readAt !== null) === read) &&
      (!category || item.category === category) &&
      (!code || item.code === code) &&
      (!path || item.path === path) &&
      (!term || matchesSearch(item, term)),
  )
}

function unreadIn(items) {
  return items.filter((item) => item.archivedAt === null && item.readAt === null)
}

function countsOf(items) {
  const unread = unreadIn(items)
  const byCategory = unread.reduce(
    (counts, item) => ({ ...counts, [item.category]: (counts[item.category] ?? 0) + 1 }),
    {},
  )
  return { count: unread.length, byCategory }
}

function newestFirst(left, right) {
  return new Date(right.createdAt) - new Date(left.createdAt)
}

export const notificationsHandlers = [
  http.get(
    '/api/v1/notifications',
    route(({ request }) => {
      const user = viewer()
      const query = new URL(request.url).searchParams
      const inbox = inboxOf(user)
      const { items, pagination } = paginate(filtered(inbox, query).sort(newestFirst), query)
      return envelope(items.map(resource), {
        pagination,
        unreadCount: unreadIn(inbox).length,
      })
    }),
  ),

  http.get(
    '/api/v1/notifications/unread-count',
    route(() => envelope(countsOf(inboxOf(viewer())))),
  ),

  http.patch(
    '/api/v1/notifications/:id/read',
    route(({ params }) => {
      const item = owned(viewer(), params.id)
      item.readAt = item.readAt ?? new Date().toISOString()
      return envelope(resource(item))
    }),
  ),

  http.patch(
    '/api/v1/notifications/:id/archive',
    route(({ params }) => {
      const item = owned(viewer(), params.id)
      const now = new Date().toISOString()
      item.readAt = item.readAt ?? now
      item.archivedAt = item.archivedAt ?? now
      return envelope(resource(item))
    }),
  ),

  http.post(
    '/api/v1/notifications/read-all',
    route(async ({ request }) => {
      const user = viewer()
      const body = (await request.json().catch(() => null)) ?? {}
      const now = new Date().toISOString()
      const targets = unreadIn(inboxOf(user)).filter(
        (item) => !body.category || item.category === body.category,
      )
      targets.forEach((item) => {
        item.readAt = now
      })
      return envelope({ updated: targets.length })
    }),
  ),

  http.get(
    '/api/v1/me/notification-preferences',
    route(() => envelope(preferencesOf(viewer()))),
  ),

  http.put(
    '/api/v1/me/notification-preferences',
    route(async ({ request }) => {
      const user = viewer()
      const body = (await request.json().catch(() => null)) ?? {}
      return envelope(updatePreferences(user, body))
    }),
  ),
]
