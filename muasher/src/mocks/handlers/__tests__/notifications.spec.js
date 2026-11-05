import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { notificationsHandlers } from '@/mocks/handlers/notifications'
import { resetNotificationsStore } from '@/mocks/stores/notificationsStore'
import { resetNotificationPreferences } from '@/mocks/stores/notificationPreferencesStore'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'

const server = setupServer(...notificationsHandlers)
const ENTITY = { id: 7601, roles: ['entity'], entityId: 6001 }
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/

async function call(method, path, body) {
  const response = await mockFetch(`${MOCK_API_BASE}${path}`, {
    method,
    headers: body ? { 'content-type': 'application/json', 'accept-language': 'en' } : {},
    body: body ? JSON.stringify(body) : undefined,
  })
  return { status: response.status, body: await response.json() }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetNotificationsStore()
  resetNotificationPreferences()
  seedMockSession(ENTITY)
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('notifications mock (Task-35 contract)', () => {
  it('lists own notifications newest first with the unread count', async () => {
    const { status, body } = await call('GET', '/notifications?per_page=3')
    expect(status).toBe(200)
    expect(body.data).toHaveLength(3)
    const [first] = body.data
    expect(first.id).toMatch(UUID)
    expect(Object.keys(first).sort()).toEqual(
      [
        'actionedAt',
        'archivedAt',
        'body',
        'category',
        'code',
        'createdAt',
        'id',
        'isRead',
        'link',
        'module',
        'path',
        'pathLabel',
        'readAt',
        'title',
      ].sort(),
    )
    expect(first.code).toBe('CNT-02')
    expect(body.meta.unreadCount).toBe(2)
    expect(body.meta.pagination).toMatchObject({ page: 1, perPage: 3, total: 6 })
  })

  it('filters unread, marks one read and archives one', async () => {
    const unread = await call('GET', '/notifications?filter[read]=false')
    expect(unread.body.data.every((item) => item.isRead === false)).toBe(true)
    const [target] = unread.body.data
    expect((await call('PATCH', `/notifications/${target.id}/read`)).body.data.isRead).toBe(true)
    expect((await call('GET', '/notifications/unread-count')).body.data.count).toBe(1)
    const archived = await call('PATCH', `/notifications/${target.id}/archive`)
    expect(archived.body.data.archivedAt).toBeTruthy()
    expect((await call('GET', '/notifications')).body.meta.pagination.total).toBe(5)
    expect((await call('GET', '/notifications?filter[archived]=true')).body.data).toHaveLength(1)
  })

  it('marks everything read and refuses unknown or foreign ids', async () => {
    expect((await call('POST', '/notifications/read-all', {})).body.data.updated).toBe(2)
    expect((await call('GET', '/notifications/unread-count')).body.data.count).toBe(0)
    expect((await call('PATCH', '/notifications/42/read')).status).toBe(404)
    clearMockSession()
    expect((await call('GET', '/notifications')).status).toBe(401)
  })

  it('reads and updates the e-mail preferences and locks mandatory types', async () => {
    const { body } = await call('GET', '/me/notification-preferences')
    const acc = body.data.find((row) => row.code === 'ACC-01')
    expect(acc).toMatchObject({ module: 'ACC', isMandatory: false })
    expect(acc.channels).toEqual([
      { channel: 'in_app', enabled: true, configurable: false },
      { channel: 'email', enabled: true, configurable: true },
    ])
    expect(body.data.some((row) => row.code === 'CNT-01')).toBe(false)
    const updated = await call('PUT', '/me/notification-preferences', {
      preferences: [{ code: 'ACC-01', channel: 'email', enabled: false }],
    })
    expect(updated.body.data.find((row) => row.code === 'ACC-01').channels[1].enabled).toBe(false)
    const locked = await call('PUT', '/me/notification-preferences', {
      preferences: [{ code: 'PAY-02', channel: 'email', enabled: false }],
    })
    expect(locked.status).toBe(422)
    expect(locked.body.code).toBe('notification_preference.locked')
    expect(locked.body.errors['preferences.0.channel']).toEqual([
      'This notification is mandatory and cannot be turned off.',
    ])
  })
})
