import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { authHandlers } from '@/mocks/handlers/auth'
import { entitiesHandlers } from '@/mocks/handlers/entities'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { findEntity } from '@/mocks/stores/entitiesStore'
import { resetAuthMock } from '@/mocks/stores/authMockReset'
import { resetIntegrationsState, smsState } from '@/mocks/stores/integrations/integrationsState'
import { DEV_OTP_CODE, MOCK_PASSWORD } from '@/mocks/fixtures/auth'
import { FIXTURE_VERIFIED_PHONE } from '@/mocks/fixtures/entities'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...authHandlers, ...entitiesHandlers)

const SYSTEM_ADMIN = { id: 2, roles: ['system_admin'] }
const SUPER_ADMIN = { id: 1, roles: [], superAdmin: true }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const NAMAA = { id: 6001, roles: ['entity'], entityId: 6001 }
const REASON = 'The client accepted the deviation in writing.'

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return {
    status: response.status,
    body: text ? JSON.parse(text) : null,
    headers: response.headers,
  }
}

function signIn(identifier, password = MOCK_PASSWORD) {
  return call('POST', '/auth/login', { identifier, password })
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetEntitiesMock()
  resetAuthMock()
  resetIntegrationsState()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('sign-in mock (Task-51 contract)', () => {
  it('serves the simplified login modes', async () => {
    const modes = await call('GET', '/auth/login-modes')
    expect(modes.body.data).toEqual({
      identifiers: ['email', 'mobile', 'unified_number'],
      entity_second_factor_enabled: true,
      otp_length: 6,
      otp_expiry_seconds: 300,
      resend_cooldown_seconds: 60,
      sso_providers: [],
    })
  })

  it.each([
    ['the e-mail', 'info@albirr-riyadh.example.sa'],
    ['the unified number', '7100005001'],
    ['the verified mobile', FIXTURE_VERIFIED_PHONE.replace('+966', '0')],
  ])('asks an entity for the e-mail code after the password with %s', async (label, identifier) => {
    const first = await signIn(identifier)
    expect(first.status).toBe(200)
    expect(first.body.data).toMatchObject({ step: 'otp_required', requires_otp: true })
    expect(first.body.data.otp).toMatchObject({ channel: 'email', length: 6 })
    const verified = await call('POST', '/auth/otp/verify', {
      challenge_id: first.body.data.otp.challenge_id,
      code: DEV_OTP_CODE,
    })
    expect(verified.body.data.step).toBe('authenticated')
    expect(verified.body.data.user.entity.id).toBe(5001)
  })

  it('answers the same generic error for a wrong password, an unknown account and an unverified mobile', async () => {
    const wrong = await signIn('info@albirr-riyadh.example.sa', 'Wrong-Password-2026')
    const unknown = await signIn('nobody@example.sa')
    const unverified = await signIn('0500006001')
    for (const answer of [wrong, unknown, unverified]) {
      expect(answer.status).toBe(422)
      expect(answer.body.code).toBe('credentials.invalid')
      expect(answer.body.errors).toEqual({})
    }
    expect(unknown.body).toEqual(wrong.body)
  })

  it('refuses a malformed identifier on its field and a missing password', async () => {
    const malformed = await call('POST', '/auth/login', { identifier: '12345', password: '' })
    expect(malformed.status).toBe(422)
    expect(Object.keys(malformed.body.errors).sort()).toEqual(['identifier', 'password'])
    expect(authHandlers.map((handler) => handler.info.path)).not.toContain(
      '/api/v1/auth/otp/request',
    )
  })

  it('locks the account after five failures of any factor and says when to retry', async () => {
    for (let attempt = 0; attempt < 4; attempt += 1)
      expect((await signIn('7100005001', 'Wrong-Password-2026')).status).toBe(422)
    const challenge = (await signIn('7100005001')).body.data.otp
    const locked = await call('POST', '/auth/otp/verify', {
      challenge_id: challenge.challenge_id,
      code: '000000',
    })
    expect(locked.status).toBe(423)
    expect(locked.body.code).toBe('account_locked')
    expect(Number(locked.headers.get('retry-after'))).toBeGreaterThan(800)
    expect((await signIn('7100005001')).status).toBe(423)
  })

  it('limits the codes of one account and refuses an early resend', async () => {
    const challenges = []
    for (let attempt = 0; attempt < 5; attempt += 1)
      challenges.push((await signIn('info@albirr-riyadh.example.sa')).body.data.otp)
    const throttled = await signIn('info@albirr-riyadh.example.sa')
    expect(throttled.status).toBe(429)
    expect(throttled.body.code).toBe('otp.cooldown')
    expect(Number(throttled.headers.get('retry-after'))).toBeGreaterThan(0)
    const early = await call('POST', '/auth/otp/resend', {
      challenge_id: challenges[4].challenge_id,
    })
    expect(early.status).toBe(429)
  })

  it('signs an entity in after the password when the setting is off, and keeps staff on TOTP', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const updated = await call('PUT', '/settings/auth', { entity_second_factor_enabled: false })
    expect(updated.body.data).toEqual({
      entity_second_factor_enabled: false,
      entity_second_factor_channel: 'email',
      staff_totp_required: true,
    })
    clearMockSession()
    expect((await signIn('7100005001')).body.data.step).toBe('authenticated')
    expect((await signIn('abdullah.alqahtani@moashir.example.sa')).body.data.step).toBe(
      'mfa_challenge',
    )
    expect((await call('GET', '/auth/login-modes')).body.data.entity_second_factor_enabled).toBe(
      false,
    )
  })
})

describe('sign-in security setting mock', () => {
  it('lets only the holder of settings.auth.manage read and change it', async () => {
    expect((await call('GET', '/settings/auth')).status).toBe(401)
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/settings/auth')).status).toBe(403)
    seedMockSession(ALBIRR)
    expect(
      (await call('PUT', '/settings/auth', { entity_second_factor_enabled: false })).status,
    ).toBe(403)
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('GET', '/settings/auth')).body.data.entity_second_factor_enabled).toBe(true)
  })

  it('refuses a non-boolean value, a short reason and a Super Admin change without reason', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const notBoolean = await call('PUT', '/settings/auth', { entity_second_factor_enabled: 'no' })
    expect(notBoolean.status).toBe(422)
    expect(notBoolean.body.errors).toHaveProperty('entity_second_factor_enabled')
    const short = await call('PUT', '/settings/auth', {
      entity_second_factor_enabled: false,
      reason: 'Too short',
    })
    expect(short.body.errors).toHaveProperty('reason')
    seedMockSession(SUPER_ADMIN)
    const missing = await call('PUT', '/settings/auth', { entity_second_factor_enabled: false })
    expect(missing.status).toBe(422)
    expect(missing.body.code).toBe('reason_required')
    const withReason = await call('PUT', '/settings/auth', {
      entity_second_factor_enabled: false,
      reason: REASON,
    })
    expect(withReason.body.data.entity_second_factor_enabled).toBe(false)
  })
})

describe('mobile verification mock', () => {
  it('sends an SMS code to the saved number, verifies it and enables mobile sign-in', async () => {
    seedMockSession(NAMAA)
    expect((await call('POST', '/me/phone/verification')).body.code).toBe('phone.missing')
    const me = (await call('GET', '/entities/me')).body.data
    const saved = await call('PUT', '/entities/me', {
      name: me.name,
      authorized_person_name: me.authorized_person_name,
      phone: '0500006001',
      entity_type: me.entity_type,
      development_sector: me.development_sector,
      org_size: me.org_size,
      founding_year: me.founding_year,
      region_id: me.region.id,
      city_id: me.city.id,
      governance_grade: null,
      employees_count: null,
      annual_beneficiaries_count: null,
      has_strategic_plan: true,
      marketing_consent: false,
    })
    expect(saved.body.data).toMatchObject({ phone: '+966500006001', phone_verified: false })
    const started = await call('POST', '/me/phone/verification')
    expect(started.body.data).toMatchObject({ channel: 'sms', length: 6 })
    const wrong = await call('POST', '/me/phone/verification/confirm', {
      challenge_id: started.body.data.challenge_id,
      code: '000000',
    })
    expect(wrong.body.code).toBe('otp.invalid')
    const confirmed = await call('POST', '/me/phone/verification/confirm', {
      challenge_id: started.body.data.challenge_id,
      code: DEV_OTP_CODE,
    })
    expect(confirmed.body.data).toMatchObject({ phone_verified: true })
    expect((await call('POST', '/me/phone/verification')).body.code).toBe('phone.already_verified')
    clearMockSession()
    expect((await signIn('0500006001')).body.data.step).toBe('otp_required')
  })

  it('answers 503 phone.sms_unavailable when no SMS provider is active', async () => {
    findEntity(6001).owner.phone = '+966500006001'
    Object.values(smsState().rows).forEach((row) => {
      if (row) row.isActive = false
    })
    seedMockSession(NAMAA)
    const unavailable = await call('POST', '/me/phone/verification')
    expect(unavailable.status).toBe(503)
    expect(unavailable.body.code).toBe('phone.sms_unavailable')
    expect(unavailable.body.message).toBe(
      'Text messages cannot be sent right now. Please try again later.',
    )
  })
})
