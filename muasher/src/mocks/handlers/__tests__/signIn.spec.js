import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
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

vi.mock('msw', async (importOriginal) => ({
  ...(await importOriginal()),
  delay: () => Promise.resolve(),
}))

const server = setupServer(...authHandlers, ...entitiesHandlers)

const SYSTEM_ADMIN = { id: 2, roles: ['system_admin'] }
const SUPER_ADMIN = { id: 1, roles: [], superAdmin: true }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const NAMAA = { id: 6001, roles: ['entity'], entityId: 6001 }
const REASON = 'The client accepted SMS sign-in codes in writing.'
const ALBIRR_IDS = {
  email: 'info@albirr-riyadh.example.sa',
  mobile: FIXTURE_VERIFIED_PHONE.replace('+966', '0'),
  unifiedNumber: '7100005001',
}
const STAFF_EMAIL = 'abdullah.alqahtani@moashir.example.sa'
const CODE_SENT_MESSAGE =
  'If an entity account matches what you entered, a sign-in code has been sent.'

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

function requestCode(identifier) {
  return call('POST', '/auth/otp/request', { identifier })
}

function verify(challenge, code = DEV_OTP_CODE) {
  return call('POST', '/auth/otp/verify', { challenge_id: challenge.challenge_id, code })
}

async function allowSms() {
  seedMockSession(SYSTEM_ADMIN)
  const saved = await call('PUT', '/settings/auth', {
    entity_sign_in_code_channels: ['email', 'sms'],
  })
  clearMockSession()
  return saved
}

function turnSmsGatewayOff() {
  Object.values(smsState().rows).forEach((row) => {
    if (row) row.isActive = false
  })
}

function shapeOf(answer) {
  const { otp, ...rest } = answer.body.data
  return { status: answer.status, rest, otpKeys: Object.keys(otp).sort(), channel: otp.channel }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetEntitiesMock()
  resetAuthMock()
  resetIntegrationsState()
})
afterEach(() => {
  vi.useRealTimers()
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('sign-in mock: password (Task-61 contract)', () => {
  it('serves the login modes with the code channels instead of the second factor', async () => {
    const modes = await call('GET', '/auth/login-modes')
    expect(modes.body.data).toEqual({
      identifiers: ['email', 'mobile', 'unified_number'],
      code_channels: ['email'],
      otp_length: 6,
      otp_expiry_seconds: 300,
      resend_cooldown_seconds: 60,
      sso_providers: [],
    })
    await allowSms()
    expect((await call('GET', '/auth/login-modes')).body.data.code_channels).toEqual([
      'email',
      'sms',
    ])
    turnSmsGatewayOff()
    expect((await call('GET', '/auth/login-modes')).body.data.code_channels).toEqual(['email'])
  })

  it.each(Object.entries(ALBIRR_IDS))(
    'signs an active entity in with the password alone using the %s',
    async (label, identifier) => {
      const answer = await signIn(identifier)
      expect(answer.status).toBe(200)
      expect(answer.body.data.step).toBe('authenticated')
      expect(answer.body.data.user.entity.id).toBe(5001)
      expect(answer.body.data).not.toHaveProperty('otp')
    },
  )

  it('still sends the registration code by e-mail to a pending entity', async () => {
    const first = await signIn('info@suqya-tabuk.example.sa')
    expect(first.body.data).toMatchObject({ step: 'otp_required', requires_otp: true })
    expect(first.body.data.otp.channel).toBe('email')
    const verified = await verify(first.body.data.otp)
    expect(verified.body.data.step).toBe('authenticated')
    expect(findEntity(7001).status).toBe('active')
  })

  it('keeps staff on the authenticator', async () => {
    expect((await signIn(STAFF_EMAIL)).body.data.step).toBe('mfa_challenge')
  })

  it('answers the same generic error for a wrong password, an unknown account and an unverified mobile', async () => {
    const wrong = await signIn(ALBIRR_IDS.email, 'Wrong-Password-2026')
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
  })

  it('locks the account after five failures across the password and the code', async () => {
    for (let attempt = 0; attempt < 4; attempt += 1)
      expect((await signIn(ALBIRR_IDS.unifiedNumber, 'Wrong-Password-2026')).status).toBe(422)
    const challenge = (await requestCode(ALBIRR_IDS.unifiedNumber)).body.data.otp
    const locked = await verify(challenge, '000000')
    expect(locked.status).toBe(423)
    expect(locked.body.code).toBe('account_locked')
    expect(Number(locked.headers.get('retry-after'))).toBeGreaterThan(800)
    expect((await signIn(ALBIRR_IDS.unifiedNumber)).status).toBe(423)
    const ignored = await requestCode(ALBIRR_IDS.email)
    expect(ignored.status).toBe(200)
    expect((await verify(ignored.body.data.otp)).body.code).toBe('otp.invalid')
  })

  it('refuses the authenticator enrolment of a signed-in entity', async () => {
    seedMockSession(ALBIRR)
    expect((await call('POST', '/auth/mfa/enroll')).status).toBe(403)
    expect((await call('POST', '/auth/mfa/confirm', { code: '246810' })).status).toBe(403)
  })
})

describe('sign-in mock: one-time code (Task-61 contract)', () => {
  it('signs an active entity in with the code and consumes it', async () => {
    const answer = await requestCode(ALBIRR_IDS.unifiedNumber)
    expect(answer.status).toBe(200)
    expect(answer.body.data).toMatchObject({
      step: 'otp_required',
      requires_otp: true,
      message: CODE_SENT_MESSAGE,
    })
    expect(answer.body.data.otp).toMatchObject({ channel: 'email', length: 6 })
    expect(JSON.stringify(answer.body)).not.toContain('albirr')
    const verified = await verify(answer.body.data.otp)
    expect(verified.body.data.step).toBe('authenticated')
    expect(verified.body.data.user.entity.id).toBe(5001)
    expect((await verify(answer.body.data.otp)).body.code).toBe('otp.challenge_invalid')
  })

  it('answers every identifier with the same shape and sends a code to entities only', async () => {
    const answers = {
      active: await requestCode(ALBIRR_IDS.email),
      unknown: await requestCode('nobody@example.sa'),
      staff: await requestCode(STAFF_EMAIL),
      deactivated: await requestCode('admin@alkhair-waqf.example.sa'),
      pending: await requestCode('info@suqya-tabuk.example.sa'),
    }
    const reference = shapeOf(answers.active)
    for (const answer of Object.values(answers)) expect(shapeOf(answer)).toEqual(reference)
    const accepted = {}
    for (const [key, answer] of Object.entries(answers))
      accepted[key] = (await verify(answer.body.data.otp)).status === 200
    expect(accepted).toEqual({
      active: true,
      unknown: false,
      staff: false,
      deactivated: false,
      pending: true,
    })
  })

  it('follows the typed identifier for the channel (R-CH) for real accounts and decoys alike', async () => {
    const channelOf = async (identifier) => (await requestCode(identifier)).body.data.otp.channel
    expect(await channelOf(ALBIRR_IDS.mobile)).toBe('email')
    await allowSms()
    expect(await channelOf(ALBIRR_IDS.mobile)).toBe('sms')
    expect(await channelOf('0599999999')).toBe('sms')
    expect(await channelOf(ALBIRR_IDS.email)).toBe('email')
    expect(await channelOf(ALBIRR_IDS.unifiedNumber)).toBe('email')
    turnSmsGatewayOff()
    expect(await channelOf('0588888888')).toBe('email')
  })

  it('limits the requests of one identifier and answers 429 rate_limited with the wait', async () => {
    for (let attempt = 0; attempt < 3; attempt += 1)
      expect((await requestCode('nobody@example.sa')).status).toBe(200)
    const limited = await requestCode('Nobody@Example.sa')
    expect(limited.status).toBe(429)
    expect(limited.body.code).toBe('rate_limited')
    expect(Number(limited.headers.get('retry-after'))).toBeGreaterThan(500)
    expect((await requestCode(ALBIRR_IDS.email)).status).toBe(200)
  })

  it('limits the requests of one address to ten in ten minutes', async () => {
    for (let index = 0; index < 10; index += 1)
      expect((await requestCode(`person${index}@example.sa`)).status).toBe(200)
    expect((await requestCode('person10@example.sa')).status).toBe(429)
  })

  it('turns the code of an account over its limit into a decoy', async () => {
    for (const identifier of [ALBIRR_IDS.email, ALBIRR_IDS.email, ALBIRR_IDS.email])
      await requestCode(identifier)
    await requestCode(ALBIRR_IDS.unifiedNumber)
    await requestCode(ALBIRR_IDS.unifiedNumber)
    const overLimit = await requestCode(ALBIRR_IDS.mobile)
    expect(overLimit.status).toBe(200)
    expect((await verify(overLimit.body.data.otp)).body.code).toBe('otp.invalid')
  })

  it('refuses a malformed identifier with the login message', async () => {
    const malformed = await requestCode('12345')
    expect(malformed.status).toBe(422)
    expect(malformed.body.errors.identifier[0]).toContain('10 digits starting with 7')
    expect((await requestCode('')).body.errors).toHaveProperty('identifier')
  })

  it('ends a challenge after five wrong codes', async () => {
    const challenge = (await requestCode('nobody@example.sa')).body.data.otp
    for (let attempt = 0; attempt < 4; attempt += 1)
      expect((await verify(challenge, '000000')).body.code).toBe('otp.invalid')
    const ended = await verify(challenge, '000000')
    expect(ended.status).toBe(423)
    expect(ended.body.code).toBe('otp.attempts_exceeded')
    expect((await verify(challenge)).body.code).toBe('otp.challenge_invalid')
  })

  it('keeps the channel of a decoy and of a real SMS code on resend', async () => {
    await allowSms()
    const decoy = (await requestCode('0599999999')).body.data.otp
    const real = (await requestCode(ALBIRR_IDS.mobile)).body.data.otp
    const early = await call('POST', '/auth/otp/resend', { challenge_id: decoy.challenge_id })
    expect(early.status).toBe(429)
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(Date.now() + 61 * 1000)
    for (const challenge of [decoy, real]) {
      const resent = await call('POST', '/auth/otp/resend', {
        challenge_id: challenge.challenge_id,
      })
      expect(resent.status).toBe(200)
      expect(resent.body.data.otp.channel).toBe('sms')
    }
  })

  it('falls back to e-mail on resend for real and decoy codes once SMS is gone', async () => {
    await allowSms()
    const decoy = (await requestCode('0599999999')).body.data.otp
    const real = (await requestCode(ALBIRR_IDS.mobile)).body.data.otp
    turnSmsGatewayOff()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(Date.now() + 61 * 1000)
    for (const challenge of [decoy, real]) {
      const resent = await call('POST', '/auth/otp/resend', {
        challenge_id: challenge.challenge_id,
      })
      expect(resent.body.data.otp.channel).toBe('email')
    }
  })

  it('sends a real SMS code by e-mail on resend once the mobile is no longer verified', async () => {
    await allowSms()
    const real = (await requestCode(ALBIRR_IDS.mobile)).body.data.otp
    findEntity(5001).owner.phoneVerifiedAt = null
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(Date.now() + 61 * 1000)
    const resent = await call('POST', '/auth/otp/resend', { challenge_id: real.challenge_id })
    expect(resent.body.data.otp.channel).toBe('email')
  })
})

describe('sign-in security setting mock', () => {
  it('lets only the holder of settings.auth.manage read and change it', async () => {
    expect((await call('GET', '/settings/auth')).status).toBe(401)
    seedMockSession(FOLLOWER)
    expect((await call('GET', '/settings/auth')).status).toBe(403)
    seedMockSession(ALBIRR)
    const denied = await call('PUT', '/settings/auth', {
      entity_sign_in_code_channels: ['email', 'sms'],
    })
    expect(denied.status).toBe(403)
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('GET', '/settings/auth')).body.data).toEqual({
      entity_sign_in_code_channels: ['email'],
      sms_status: 'test',
      staff_totp_required: true,
    })
  })

  it('saves the channels, reports the SMS status and keeps an unchanged value', async () => {
    const saved = await allowSms()
    expect(saved.status).toBe(200)
    expect(saved.body.data.entity_sign_in_code_channels).toEqual(['email', 'sms'])
    smsState().mode = 'live'
    seedMockSession(SYSTEM_ADMIN)
    expect((await call('GET', '/settings/auth')).body.data.sms_status).toBe('live')
    turnSmsGatewayOff()
    expect((await call('GET', '/settings/auth')).body.data).toMatchObject({
      entity_sign_in_code_channels: ['email', 'sms'],
      sms_status: 'unavailable',
    })
    const emailOnly = await call('PUT', '/settings/auth', {
      entity_sign_in_code_channels: ['email'],
    })
    expect(emailOnly.body.data.entity_sign_in_code_channels).toEqual(['email'])
  })

  it.each([
    [[], 'entity_sign_in_code_channels', 'This field is required.'],
    ['email', 'entity_sign_in_code_channels', 'The value is not valid.'],
    [['email', 'sms', 'sms'], 'entity_sign_in_code_channels', 'The value is not valid.'],
    [['email', 'fax'], 'entity_sign_in_code_channels.1', 'The value is not valid.'],
    [['email', 'email'], 'entity_sign_in_code_channels.1', 'The value is not valid.'],
    [
      ['sms'],
      'entity_sign_in_code_channels',
      'E-mail must stay allowed: every entity can always receive its sign-in code by e-mail.',
    ],
  ])('refuses the channels %j on %s', async (channels, field, message) => {
    seedMockSession(SYSTEM_ADMIN)
    const refused = await call('PUT', '/settings/auth', { entity_sign_in_code_channels: channels })
    expect(refused.status).toBe(422)
    expect(refused.body.errors).toEqual({ [field]: [message] })
  })

  it('refuses SMS while the gateway is not configured', async () => {
    turnSmsGatewayOff()
    seedMockSession(SYSTEM_ADMIN)
    const refused = await call('PUT', '/settings/auth', {
      entity_sign_in_code_channels: ['email', 'sms'],
    })
    expect(refused.status).toBe(422)
    expect(refused.body.errors.entity_sign_in_code_channels).toEqual([
      'Configure the SMS gateway before allowing sign-in codes by text message.',
    ])
  })

  it('refuses a short reason and a Super Admin change without reason', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const short = await call('PUT', '/settings/auth', {
      entity_sign_in_code_channels: ['email', 'sms'],
      reason: 'Too short',
    })
    expect(short.body.errors).toHaveProperty('reason')
    seedMockSession(SUPER_ADMIN)
    const missing = await call('PUT', '/settings/auth', {
      entity_sign_in_code_channels: ['email', 'sms'],
    })
    expect(missing.status).toBe(422)
    expect(missing.body.code).toBe('reason_required')
    const withReason = await call('PUT', '/settings/auth', {
      entity_sign_in_code_channels: ['email', 'sms'],
      reason: REASON,
    })
    expect(withReason.body.data.entity_sign_in_code_channels).toEqual(['email', 'sms'])
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
    expect((await signIn('0500006001')).body.data.step).toBe('authenticated')
  })

  it('answers 503 phone.sms_unavailable when no SMS provider is active', async () => {
    findEntity(6001).owner.phone = '+966500006001'
    turnSmsGatewayOff()
    seedMockSession(NAMAA)
    const unavailable = await call('POST', '/me/phone/verification')
    expect(unavailable.status).toBe(503)
    expect(unavailable.body.code).toBe('phone.sms_unavailable')
    expect(unavailable.body.message).toBe(
      'Text messages cannot be sent right now. Please try again later.',
    )
  })
})
