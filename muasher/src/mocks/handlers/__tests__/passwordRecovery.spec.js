import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetStaffUsers } from '@/mocks/stores/staffUsers'
import { resetTermsStore } from '@/mocks/stores/termsStore'
import { resetPasswordResetLinks } from '@/mocks/stores/passwordResetLinks'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'
import { MOCK_PASSWORD } from '@/mocks/fixtures/auth'

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
  resetPasswordResetLinks()
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

  it('resets from the link alone, once, and refuses expired or used links and weak passwords', async () => {
    const body = { token: 'a1b2c3d4e5f6', password: STRONG, password_confirmation: STRONG }
    const check = (token) => call('POST', '/auth/password/reset/check', { token })
    expect((await check('a1b2c3d4e5f6')).body.data).toEqual({ usable: true })
    expect((await call('POST', '/auth/password/reset', body)).status).toBe(200)
    expect((await call('POST', '/auth/password/reset', body)).body.code).toBe('token.invalid')
    expect((await check('a1b2c3d4e5f6')).body.code).toBe('token.invalid')
    for (const token of ['expired-reset-link-2026', 'used-reset-link-2026']) {
      expect((await check(token)).body.code).toBe('token.invalid')
      expect((await call('POST', '/auth/password/reset', { ...body, token })).body.code).toBe(
        'token.invalid',
      )
    }
    const weak = await call('POST', '/auth/password/reset', {
      ...body,
      token: 'b2c3d4e5f6a7',
      password: 'short',
      password_confirmation: 'short',
    })
    expect(weak.body.errors.password).toHaveLength(1)
    expect((await check('b2c3d4e5f6a7')).body.data).toEqual({ usable: true })
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

  it('answers the invitation check like the API: usable, expired or invalid', async () => {
    const check = (token) => call('GET', `/auth/invitations/${token}`)
    expect((await check('invite-sarah-whitfield-2026')).body.data).toEqual({
      usable: true,
      national_id_masked: null,
      nda: expect.objectContaining({ id: 501, version: '1.0' }),
    })
    expect((await check('invite-expired-2026')).status).toBe(410)
    const used = await check('invite-used-2026')
    expect(used.status).toBe(404)
    expect(used.body.code).toBe('invitation.invalid')
  })

  it('activates an invited account with the current terms and asks for two-factor enrolment', async () => {
    const accept = (token, extra = {}) =>
      call('POST', `/auth/invitations/${token}/accept`, {
        password: STRONG,
        password_confirmation: STRONG,
        terms_version_id: 3,
        nda_version_id: 501,
        ...extra,
      })
    expect((await accept('invite-expired-2026')).status).toBe(410)
    expect((await accept('invite-used-2026')).body.code).toBe('invitation.invalid')
    expect((await accept('invite-sarah-whitfield-2026', { terms_version_id: 99 })).status).toBe(422)
    expect((await accept('invite-sarah-whitfield-2026', { nda_version_id: null })).status).toBe(422)
    const activated = await accept('invite-sarah-whitfield-2026')
    expect(activated.body.data.step).toBe('mfa_enrollment')
    expect((await accept('invite-sarah-whitfield-2026')).status).toBe(404)
  })

  it('changes the signed-in user own password after checking the current one', async () => {
    const change = (extra = {}) =>
      call('POST', '/auth/password/change', {
        current_password: MOCK_PASSWORD,
        password: STRONG,
        password_confirmation: STRONG,
        ...extra,
      })
    expect((await change()).status).toBe(401)
    seedMockSession({ id: 8, roles: ['assessor'] })
    const wrongCurrent = await change({ current_password: 'Not-The-Password-1' })
    expect(wrongCurrent.status).toBe(422)
    expect(wrongCurrent.body.errors).toHaveProperty('current_password')
    const reused = await change({ password: MOCK_PASSWORD, password_confirmation: MOCK_PASSWORD })
    expect(reused.body.errors).toHaveProperty('password')
    expect((await change()).status).toBe(204)
  })
})
