import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { contactMessagesHandlers } from '@/mocks/handlers/contactMessages'
import { pathGuidesHandlers } from '@/mocks/handlers/pathGuides'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'

const server = setupServer(...contactMessagesHandlers, ...pathGuidesHandlers)

async function get(path) {
  const response = await mockFetch(`${MOCK_API_BASE}${path}`)
  return { status: response.status, body: await response.json() }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('sender messages mock (B-03 counts)', () => {
  it('counts unread replies and statuses for an entity sender', async () => {
    seedMockSession({ id: 7601, roles: ['entity'], entityId: 6001 })
    const { status, body } = await get('/me/contact-messages?per_page=1')
    expect(status).toBe(200)
    expect(body.data).toHaveLength(1)
    expect(body.meta.unreadReplies).toBe(1)
    expect(body.meta.statusCounts).toEqual({ new: 0, inProgress: 1, replied: 1, closed: 1 })
    expect(body.data[0]).toMatchObject({ reference: 'CNT-2026-000052', hasUnreadReply: true })
  })

  it('filters by a list of statuses and refuses guests', async () => {
    seedMockSession({ id: 7601, roles: ['entity'], entityId: 6001 })
    const closed = await get('/me/contact-messages?filter[status]=closed')
    expect(closed.body.data.map((thread) => thread.status)).toEqual(['closed'])
    clearMockSession()
    expect((await get('/me/contact-messages')).status).toBe(401)
  })
})

describe('path guide mock', () => {
  it('serves the accredited guide texts and 404 for unknown paths', async () => {
    const { body } = await get('/content/assessment-paths/accredited')
    expect(body.data.entityTexts.instructions.ar.split('\n')).toHaveLength(4)
    expect((await get('/content/assessment-paths/self')).status).toBe(404)
  })
})
