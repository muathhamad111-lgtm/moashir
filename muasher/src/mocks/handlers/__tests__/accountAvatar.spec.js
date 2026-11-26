import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { accountAvatarHandlers } from '@/mocks/handlers/accountAvatar'
import { authHandlers } from '@/mocks/handlers/auth'
import { forgetPhotos, photoIssue, storePhoto } from '@/mocks/stores/accountAvatars'
import { FIXTURE_ENTITY_LOGO_FILE_ID } from '@/mocks/fixtures/entityLogos'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...accountAvatarHandlers, ...authHandlers)

const STAFF = { id: 2, roles: ['system_admin'] }
const ASSESSOR = { id: 31, roles: ['assessor'] }
const ENTITY_WITH_LOGO = { id: 6003, roles: ['entity'], entityId: 6003 }
const ENTITY_WITHOUT_LOGO = { id: 6004, roles: ['entity'], entityId: 6004 }

function photo(type = 'image/png', size = 4) {
  return new File([new Uint8Array(size)], 'photo.png', { type })
}

async function send(method) {
  const response = await wireFetch(`${MOCK_API_BASE}/me/avatar`, {
    method,
    headers: { accept: 'application/json' },
  })
  return { status: response.status, body: await response.json() }
}

async function sessionAvatar() {
  const response = await wireFetch(`${MOCK_API_BASE}/auth/session`)
  return (await response.json()).data.avatar
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => forgetPhotos())
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('mock /me/avatar', () => {
  it('requires a session', async () => {
    expect((await send('POST')).status).toBe(401)
    expect((await send('DELETE')).status).toBe(401)
    expect((await wireFetch(`${MOCK_API_BASE}/me/avatar`)).status).toBe(401)
  })

  it('stores a staff photo, links it from the session and streams it back', async () => {
    seedMockSession(STAFF)
    expect(await sessionAvatar()).toBeNull()

    const saved = await storePhoto(STAFF, photo())
    expect(saved).toMatchObject({ source: 'photo' })
    expect(saved.url).toMatch(/^\/api\/v1\/me\/avatar\?v=/)
    expect(await sessionAvatar()).toEqual(saved)

    const image = await wireFetch(`${window.location.origin}${saved.url}`)
    expect(image.status).toBe(200)
    expect(image.headers.get('content-type')).toBe('image/png')
  })

  it('refuses a missing, non-image or oversized file', () => {
    expect(photoIssue(null)).toBeTruthy()
    expect(photoIssue(new File(['<svg/>'], 'photo.svg', { type: 'image/svg+xml' }))).toBeTruthy()
    expect(photoIssue(photo('image/jpeg', 2 * 1024 * 1024 + 1))).toBeTruthy()
    expect(photoIssue(photo())).toBeNull()
  })

  it('removes the photo and keeps photos per account', async () => {
    await storePhoto(STAFF, photo())
    seedMockSession(ASSESSOR)
    expect(await sessionAvatar()).toBeNull()
    expect((await wireFetch(`${MOCK_API_BASE}/me/avatar`)).status).toBe(404)

    seedMockSession(STAFF)
    const removed = await send('DELETE')
    expect(removed.status).toBe(200)
    expect(removed.body.data.avatar).toBeNull()
    expect(await sessionAvatar()).toBeNull()
  })

  it('uses the entity logo for entity accounts and refuses them a personal photo', async () => {
    seedMockSession(ENTITY_WITH_LOGO)
    expect(await sessionAvatar()).toEqual({
      source: 'entity_logo',
      url: `/api/v1/me/avatar?v=${FIXTURE_ENTITY_LOGO_FILE_ID}`,
    })
    expect((await wireFetch(`${MOCK_API_BASE}/me/avatar`)).status).toBe(200)
    expect((await send('POST')).status).toBe(403)
    expect((await send('DELETE')).status).toBe(403)

    seedMockSession(ENTITY_WITHOUT_LOGO)
    expect(await sessionAvatar()).toBeNull()
  })
})
