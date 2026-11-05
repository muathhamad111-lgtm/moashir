import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetStaffUsers } from '@/mocks/stores/staffUsers'
import { resetTermsStore } from '@/mocks/stores/termsStore'
import { MOCK_API_BASE, clearMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)
const STRONG = 'Namaa-Strong-2026!'

async function call(method, path, body) {
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, {
    method,
    headers: { 'accept-language': 'en', 'content-type': 'application/json' },
    body: JSON.stringify(body),
  })
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetStaffUsers()
  resetTermsStore()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('password recovery mock', () => {
  it('answers the same neutral message for known and unknown e-mails', async () => {
    const known = await call('POST', '/auth/password/forgot', {
      email: 'abdullah.alqahtani@moashir.example.sa',
    })
    const unknown = await call('POST', '/auth/password/forgot', { email: 'nobody@example.sa' })
    expect(known.status).toBe(200)
    expect(unknown.body.data).toEqual(known.body.data)
    expect((await call('POST', '/auth/password/forgot', { email: 'x' })).status).toBe(422)
  })

  it('resets with a valid link and refuses expired links and weak passwords', async () => {
    const body = {
      token: 'a1b2c3d4e5f6',
      email: 'abdullah.alqahtani@moashir.example.sa',
      password: STRONG,
      password_confirmation: STRONG,
    }
    expect((await call('POST', '/auth/password/reset', body)).status).toBe(200)
    const expired = await call('POST', '/auth/password/reset', {
      ...body,
      token: 'expired-reset-link-2026',
    })
    expect(expired.body.code).toBe('token.invalid')
    const weak = await call('POST', '/auth/password/reset', {
      ...body,
      password: 'short',
      password_confirmation: 'short',
    })
    expect(weak.body.errors.password).toHaveLength(1)
  })

  it('activates an invited account with the current terms and asks for two-factor enrolment', async () => {
    const accept = (token, extra = {}) =>
      call('POST', `/auth/invitations/${token}/accept`, {
        password: STRONG,
        password_confirmation: STRONG,
        terms_version_id: 3,
        ...extra,
      })
    expect((await accept('invite-expired-2026')).status).toBe(410)
    expect((await accept('invite-used-2026')).body.code).toBe('invitation.invalid')
    expect((await accept('invite-sarah-whitfield-2026', { terms_version_id: 99 })).status).toBe(422)
    const activated = await accept('invite-sarah-whitfield-2026')
    expect(activated.body.data.step).toBe('mfa_enrollment')
    expect((await accept('invite-sarah-whitfield-2026')).status).toBe(404)
  })
})
