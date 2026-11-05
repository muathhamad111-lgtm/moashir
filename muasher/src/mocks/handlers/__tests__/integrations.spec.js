import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { integrationsHandlers } from '@/mocks/handlers/integrations'
import { paymentIntegrationHandlers } from '@/mocks/handlers/paymentIntegration'
import { smsIntegrationHandlers } from '@/mocks/handlers/smsIntegration'
import { emailIntegrationHandlers } from '@/mocks/handlers/emailIntegration'
import { aiIntegrationHandlers } from '@/mocks/handlers/aiIntegration'
import { resetIntegrationsMock } from '@/mocks/stores/integrations/integrationsMockReset'
import { MOCK_ENVIRONMENT_COOKIE } from '@/mocks/stores/integrations/mockEnvironment'
import { storeAssociationFile } from '@/mocks/stores/integrations/paymentIntegration'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(
  ...integrationsHandlers,
  ...paymentIntegrationHandlers,
  ...smsIntegrationHandlers,
  ...emailIntegrationHandlers,
  ...aiIntegrationHandlers,
)

const SYSTEM_ADMIN = { id: 2, name: 'منيرة بنت خالد العتيبي', roles: ['system_admin'] }
const SUPER_ADMIN = { id: 1, roles: [], superAdmin: true }
const CONTENT_OFFICER = { id: 40, roles: ['content_officer'] }
const FINANCE = { id: 31, roles: ['finance_officer'] }
const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }

const FAKE = {
  testSecret: 'sk_test_fake_example_replacement_9876',
  liveSecret: 'sk_live_fake_example_replacement_5432',
  livePublishable: 'pk_live_fake_example_moashir02',
  revokedLive: 'sk_live_fake_example_revoked_000001',
  taqnyatToken: 'taqnyat_fake_example_bearer_token_0001',
  aiKey: 'openai_fake_example_project_key_0001',
  smtpPassword: 'fake example mailbox passphrase',
}

const REASON = 'Checkout rehearsal before the award cycle opens.'

const SECRET_STATE_KEYS = ['set', 'last4', 'updated_at', 'updated_by']
const LAST_TEST_KEYS = ['at', 'ok', 'error_kind', 'latency_ms', 'by']
const RESULT_KEYS = ['ok', 'provider', 'mode', 'latency_ms', 'checks', 'info', 'error_kind']

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  const json =
    /json/.test(response.headers.get('content-type') ?? '') && text ? JSON.parse(text) : text
  return { status: response.status, body: json, headers: response.headers }
}

const as = (descriptor) => seedMockSession(descriptor)
const keysOf = (object) => Object.keys(object).sort()
const sorted = (keys) => [...keys].sort()

function expectNoSecret(body, secret) {
  expect(JSON.stringify(body)).not.toContain(secret)
}

async function storeLiveKeys(secret = FAKE.liveSecret) {
  return call('PUT', '/settings/payment', {
    live: { publishable_key: FAKE.livePublishable, secret_key: secret },
  })
}

function setProduction() {
  document.cookie = `${MOCK_ENVIRONMENT_COOKIE}=production; path=/`
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => resetIntegrationsMock())
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  document.cookie = `${MOCK_ENVIRONMENT_COOKIE}=; path=/; max-age=0`
})
afterAll(() => server.close())

describe('integrations overview', () => {
  it('lists the managed kinds with configured and the production test-mode flag only', async () => {
    as(SYSTEM_ADMIN)
    const { status, body } = await call('GET', '/settings/integrations')
    expect(status).toBe(200)
    expect(body.data.map((row) => row.kind)).toEqual(['payment', 'sms', 'email', 'ai'])
    body.data.forEach((row) =>
      expect(keysOf(row)).toEqual(
        sorted(['kind', 'provider', 'active', 'mode', 'source', 'configured', 'last_test']),
      ),
    )
    expect(body.data[0]).toMatchObject({
      provider: 'moyasar',
      active: true,
      configured: true,
      source: 'settings',
    })
    expect(keysOf(body.data[0].last_test)).toEqual(sorted(LAST_TEST_KEYS))
    expect(body.data[2]).toMatchObject({
      provider: 'smtp',
      source: 'environment',
      configured: true,
      last_test: null,
    })
    expect(body.data[3]).toMatchObject({
      provider: null,
      source: 'none',
      configured: false,
      active: false,
    })
    expect(body.meta).toEqual({ payment_test_mode_in_production: false })
  })

  it('denies the content officer, the finance officer, an entity and a guest', async () => {
    for (const actor of [CONTENT_OFFICER, FINANCE, ENTITY]) {
      as(actor)
      for (const path of [
        '/settings/integrations',
        '/settings/payment',
        '/settings/sms',
        '/settings/email',
        '/settings/ai',
      ]) {
        expect((await call('GET', path)).status).toBe(403)
      }
    }
    clearMockSession()
    expect((await call('GET', '/settings/integrations')).status).toBe(401)
  })

  it('flags payments in test mode on production', async () => {
    as(SYSTEM_ADMIN)
    setProduction()
    expect(
      (await call('GET', '/settings/integrations')).body.meta.payment_test_mode_in_production,
    ).toBe(true)
  })
})

describe('payment gateway settings', () => {
  it('answers the GET shape of the contract', async () => {
    as(SYSTEM_ADMIN)
    const { data } = (await call('GET', '/settings/payment')).body
    expect(keysOf(data)).toEqual(
      sorted([
        'provider',
        'mode',
        'active',
        'source',
        'is_test_mode_in_production',
        'checkout_pattern',
        'test',
        'live',
        'methods',
        'supported_networks',
        'apple_pay_label',
        'confirmations',
        'linked_paths',
        'apple_pay_association_file',
        'values_to_copy',
        'checklist',
      ]),
    )
    expect(keysOf(data.test)).toEqual(
      sorted([
        'configured',
        'active',
        'publishable_key',
        'secret_key',
        'webhook_secret',
        'webhook',
        'last_test',
      ]),
    )
    expect(data.test).toMatchObject({
      configured: true,
      active: true,
      webhook: {
        id: expect.any(String),
        url: expect.any(String),
        registered_at: expect.any(String),
      },
    })
    expect(data.live).toMatchObject({
      configured: false,
      active: false,
      publishable_key: null,
      webhook: null,
      last_test: null,
    })
    expect(keysOf(data.test.secret_key)).toEqual(sorted(SECRET_STATE_KEYS))
    expect(data.test.secret_key).toEqual({
      set: true,
      last4: 'ex01',
      updated_at: expect.any(String),
      updated_by: { id: 2, name: 'منيرة بنت خالد العتيبي' },
    })
    expect(data.test.webhook_secret.last4).toBeNull()
    expect(data.confirmations.apple_pay_domain).toEqual({
      confirmed: false,
      confirmed_at: null,
      confirmed_by: null,
    })
    expect(data.apple_pay_association_file).toEqual({
      set: false,
      size_bytes: null,
      sha256: null,
      uploaded_at: null,
      uploaded_by: null,
    })
    expect(data.values_to_copy).toEqual({
      webhook_url: 'https://moashir.ttshub.live/api/v1/webhooks/moyasar',
      webhook_method: 'POST',
      webhook_events: 'all',
      apple_pay_host: 'moashir.ttshub.live',
      apple_pay_association_url:
        'https://moashir.ttshub.live/.well-known/apple-developer-merchantid-domain-association',
      egress_ips: ['203.0.113.24'],
    })
    expect(data.checklist.map((item) => item.key)).toEqual([
      'live_account_activation',
      'apple_pay_domain',
      'stcpay_activation',
      'ip_whitelist',
      'manual_webhook',
    ])
    expect(data.checklist[1]).toEqual({
      key: 'apple_pay_domain',
      confirmable: true,
      confirmed: false,
    })
    expect(data.checklist[0]).toEqual({
      key: 'live_account_activation',
      confirmable: false,
      confirmed: null,
    })
  })

  it('keeps omitted fields on a partial PUT and replaces or clears a secret without echoing it', async () => {
    as(SYSTEM_ADMIN)
    const partial = await call('PUT', '/settings/payment', { supported_networks: ['mada'] })
    expect(partial.body.data).toMatchObject({
      mode: 'test',
      active: true,
      supported_networks: ['mada'],
      test: { secret_key: { last4: 'ex01' } },
    })

    const replaced = await call('PUT', '/settings/payment', {
      test: { secret_key: FAKE.testSecret },
    })
    expect(replaced.status).toBe(200)
    expectNoSecret(replaced.body, FAKE.testSecret)
    expect(replaced.body.data.test.secret_key.last4).toBe('9876')
    expect(replaced.body.data.test.last_test).toBeNull()

    const cleared = await call('PUT', '/settings/payment', {
      active: false,
      test: { secret_key: { clear: true } },
    })
    expect(cleared.body.data.test).toMatchObject({ configured: false, secret_key: { set: false } })
  })

  it('validates key prefixes per mode as field errors', async () => {
    as(SYSTEM_ADMIN)
    const { status, body } = await call('PUT', '/settings/payment', {
      live: {
        publishable_key: 'pk_test_wrong_prefix_value',
        secret_key: 'sk_test_wrong_prefix_value_1',
      },
    })
    expect(status).toBe(422)
    expect(body.code).toBe('validation_failed')
    expect(Object.keys(body.errors)).toEqual(['live.publishable_key', 'live.secret_key'])
  })

  it('refuses live until the live pair is complete and tested, then allows it', async () => {
    as(SYSTEM_ADMIN)
    const incomplete = await call('PUT', '/settings/payment', { mode: 'live' })
    expect(incomplete.status).toBe(422)
    expect(incomplete.body).toMatchObject({
      code: 'integration.configuration_incomplete',
      errors: { mode: [expect.any(String)] },
    })
    await storeLiveKeys()
    expect((await call('PUT', '/settings/payment', { mode: 'live' })).body.code).toBe(
      'integration.test_required',
    )
    const test = await call('POST', '/settings/payment/test-connection', { mode: 'live' })
    expect(test.status).toBe(200)
    expect(keysOf(test.body.data)).toEqual(sorted(RESULT_KEYS))
    expect(test.body.data.checks.map((item) => item.key)).toEqual([
      'key_prefixes',
      'secret_key_valid',
      'intents_available',
    ])
    expect(test.body.data.info).toEqual({
      webhook_registered: false,
      publishable_key_check: 'validated_at_first_checkout',
    })
    const live = await call('PUT', '/settings/payment', { mode: 'live' })
    expect(live.body.data).toMatchObject({
      mode: 'live',
      active: true,
      live: { active: true },
      test: { active: false },
    })
  })

  it('answers 502 with the failed result and records the failed test', async () => {
    as(SYSTEM_ADMIN)
    await storeLiveKeys(FAKE.revokedLive)
    const { status, body } = await call('POST', '/settings/payment/test-connection', {
      mode: 'live',
    })
    expect(status).toBe(502)
    expect(body.code).toBe('provider_error')
    expect(body.errors.provider_error_kind).toEqual(['auth_failed'])
    expect(body.meta.result).toMatchObject({
      ok: false,
      error_kind: 'auth_failed',
      checks: [
        { key: 'key_prefixes', ok: true },
        { key: 'secret_key_valid', ok: false, detail_code: 'auth_failed' },
      ],
    })
    expectNoSecret(body, FAKE.revokedLive)
    const after = await call('GET', '/settings/payment')
    expect(after.body.data.live.last_test).toMatchObject({
      ok: false,
      error_kind: 'auth_failed',
      by: { id: 2 },
    })
  })

  it('answers 409 when the tested mode is not configured and limits tests to three per minute', async () => {
    as(SYSTEM_ADMIN)
    expect(
      (await call('POST', '/settings/payment/test-connection', { mode: 'live' })).body,
    ).toMatchObject({ code: 'integration.not_configured', meta: { kind: 'payment' } })
    for (let attempt = 0; attempt < 2; attempt += 1) {
      expect(
        (await call('POST', '/settings/payment/test-connection', { mode: 'test' })).status,
      ).toBe(200)
    }
    const limited = await call('POST', '/settings/payment/test-connection', { mode: 'test' })
    expect(limited.status).toBe(429)
    expect(Number(limited.headers.get('retry-after'))).toBeGreaterThan(0)
  })

  it('asks for a reason when production leaves live mode', async () => {
    as(SYSTEM_ADMIN)
    await storeLiveKeys()
    await call('POST', '/settings/payment/test-connection', { mode: 'live' })
    await call('PUT', '/settings/payment', { mode: 'live' })
    setProduction()
    const refused = await call('PUT', '/settings/payment', { mode: 'test' })
    expect(refused.status).toBe(422)
    expect(refused.body).toMatchObject({
      code: 'reason_required',
      errors: { reason: [expect.any(String)] },
    })
    const accepted = await call('PUT', '/settings/payment', { mode: 'test', reason: REASON })
    expect(accepted.body.data).toMatchObject({ mode: 'test', is_test_mode_in_production: true })
  })

  it('requires a reason from the super admin when a secret changes or the webhook is registered', async () => {
    as(SUPER_ADMIN)
    const body = { test: { secret_key: FAKE.testSecret } }
    expect((await call('PUT', '/settings/payment', body)).body.code).toBe('reason_required')
    expect((await call('PUT', '/settings/payment', { ...body, reason: REASON })).status).toBe(200)
    expect(
      (await call('POST', '/settings/payment/webhook/register', { mode: 'test' })).body.code,
    ).toBe('reason_required')
  })

  it('gates Apple Pay on its confirmation and refuses to withdraw a confirmation in use', async () => {
    as(SYSTEM_ADMIN)
    const refused = await call('PUT', '/settings/payment', { methods: ['creditcard', 'applepay'] })
    expect(refused.body).toMatchObject({
      code: 'payment.method_requires_confirmation',
      errors: { methods: [expect.any(String)] },
    })
    const accepted = await call('PUT', '/settings/payment', {
      methods: ['creditcard', 'applepay'],
      confirmations: { apple_pay_domain: true },
    })
    expect(accepted.body.data.confirmations.apple_pay_domain).toMatchObject({
      confirmed: true,
      confirmed_at: expect.any(String),
      confirmed_by: { id: 2 },
    })
    expect(accepted.body.data.checklist[1].confirmed).toBe(true)
    const withdrawn = await call('PUT', '/settings/payment', {
      confirmations: { apple_pay_domain: false },
    })
    expect(withdrawn.body).toMatchObject({
      code: 'payment.confirmation_in_use',
      errors: { 'confirmations.apple_pay_domain': [expect.any(String)] },
    })
  })

  it('registers the webhook and manages the Apple Pay association file', async () => {
    as(SYSTEM_ADMIN)
    const webhook = await call('POST', '/settings/payment/webhook/register', { mode: 'test' })
    expect(webhook.status).toBe(201)
    expect(webhook.body.data).toEqual({
      mode: 'test',
      webhook_id: expect.any(String),
      url: 'https://moashir.ttshub.live/api/v1/webhooks/moyasar',
    })
    expect(
      (await call('POST', '/settings/payment/apple-pay/association-file/check')).body.code,
    ).toBe('payment.apple_pay_file_missing')
    expect((await call('DELETE', '/settings/payment/apple-pay/association-file')).status).toBe(404)
    storeAssociationFile(
      { sizeBytes: 46, sha256: 'a'.repeat(64) },
      { id: 2, name: 'منيرة بنت خالد العتيبي' },
    )
    expect(
      (await call('GET', '/settings/payment')).body.data.apple_pay_association_file,
    ).toMatchObject({ set: true, size_bytes: 46, uploaded_by: { id: 2 } })
    expect(
      (await call('POST', '/settings/payment/apple-pay/association-file/check')).body.data,
    ).toEqual({
      url: 'https://moashir.ttshub.live/.well-known/apple-developer-merchantid-domain-association',
      reachable: true,
      status: 200,
      matches: true,
    })
    expect((await call('DELETE', '/settings/payment/apple-pay/association-file')).status).toBe(204)
  })
})

describe('sms settings', () => {
  it('answers the GET shape of the contract with the single verification-code template', async () => {
    as(SYSTEM_ADMIN)
    const { data } = (await call('GET', '/settings/sms')).body
    expect(keysOf(data)).toEqual(
      sorted([
        'active_provider',
        'mode',
        'source',
        'test_recipients',
        'providers',
        'templates',
        'checklist',
      ]),
    )
    expect(data.active_provider).toBe('taqnyat')
    expect(keysOf(data.providers.taqnyat)).toEqual(
      sorted(['configured', 'bearer_token', 'sender_name', 'last_test']),
    )
    expect(keysOf(data.providers.msegat)).toEqual(
      sorted([
        'configured',
        'username',
        'api_key',
        'sender_name',
        'template_restricted',
        'approved_templates',
        'approved_templates_loaded_at',
        'last_test',
      ]),
    )
    expect(data.providers.msegat).toMatchObject({
      configured: true,
      approved_templates: [],
      approved_templates_loaded_at: null,
      last_test: { ok: false, error_kind: 'unavailable' },
    })
    expect(data.templates).toHaveLength(1)
    expect(data.templates[0]).toMatchObject({
      key: 'otp_code',
      placeholders: ['code', 'validity', 'minutes'],
      required_placeholders: ['code'],
      single_segment: true,
      matches_msegat_template: null,
    })
    expect(keysOf(data.templates[0])).toEqual(
      sorted([
        'key',
        'label',
        'body',
        'default_body',
        'placeholders',
        'required_placeholders',
        'single_segment',
        'matches_msegat_template',
      ]),
    )
    expect(data.checklist.map((item) => item.key)).toEqual([
      'sender_registration',
      'api_sending_and_ip',
      'msegat_templates',
      'balance',
    ])
  })

  it('switches the active provider through null and refuses an incomplete provider', async () => {
    as(SYSTEM_ADMIN)
    expect(
      (await call('PUT', '/settings/sms', { active_provider: null })).body.data.active_provider,
    ).toBeNull()
    await call('PUT', '/settings/sms', { providers: { msegat: { sender_name: null } } })
    const refused = await call('PUT', '/settings/sms', { active_provider: 'msegat' })
    expect(refused.body).toMatchObject({
      code: 'integration.configuration_incomplete',
      errors: { active_provider: [expect.any(String)] },
    })
  })

  it('accepts Saudi mobile test numbers only', async () => {
    as(SYSTEM_ADMIN)
    const invalid = await call('PUT', '/settings/sms', {
      test_recipients: ['+966500000001', '+201000000001'],
    })
    expect(invalid.status).toBe(422)
    expect(Object.keys(invalid.body.errors)).toEqual(['test_recipients.1'])
  })

  it('loads Taqnyat senders and runs both provider tests with their checks', async () => {
    as(SYSTEM_ADMIN)
    expect((await call('GET', '/settings/sms/taqnyat/senders')).body.data[0]).toEqual({
      sender_name: 'Moashir',
      status: 'active',
      destination: 'local',
    })
    const taqnyat = await call('POST', '/settings/sms/taqnyat/test-connection')
    expect(taqnyat.body.data.checks.map((item) => item.key)).toEqual([
      'service_status',
      'credentials_valid',
      'account_usable',
      'sender_active',
    ])
    expect(taqnyat.body.data.info).toEqual({
      balance: '1250.00',
      currency: 'SAR',
      account_status: 'active',
      account_expiry_date: '2027-06-30',
    })
    const msegat = await call('POST', '/settings/sms/msegat/test-connection')
    expect(msegat.body.data.checks.map((item) => item.key)).toEqual([
      'credentials_valid',
      'account_usable',
    ])
    expect(msegat.body.data.info).toEqual({ balance: '830', sender_verified_by: 'test_sms' })
  })

  it('refuses a test SMS to a number outside the test list in test mode', async () => {
    as(SYSTEM_ADMIN)
    const refused = await call('POST', '/settings/sms/test', {
      recipient: '+966500000009',
      provider: 'taqnyat',
    })
    expect(refused.status).toBe(422)
    expect(refused.body).toMatchObject({
      code: 'sms.recipient_not_in_test_numbers',
      errors: { recipient: [expect.any(String)] },
    })
    const sent = await call('POST', '/settings/sms/test', { recipient: '+966500000001' })
    expect(sent.body.data).toEqual({
      provider: 'taqnyat',
      status: 'sent',
      provider_message_id: expect.any(String),
    })
  })

  it('checks the template against the loaded Msegat templates when the sender is restricted', async () => {
    as(SYSTEM_ADMIN)
    const notLoaded = await call('PUT', '/settings/sms', {
      providers: { msegat: { template_restricted: true } },
    })
    expect(notLoaded.body).toMatchObject({
      code: 'sms.templates_not_loaded',
      errors: { templates: [expect.any(String)] },
    })
    const loaded = await call('GET', '/settings/sms/msegat/templates')
    expect(loaded.body.data[0]).toEqual({
      id: '101',
      title: expect.any(String),
      body: expect.stringContaining('{{1}}'),
    })
    const restricted = await call('PUT', '/settings/sms', {
      providers: { msegat: { template_restricted: true } },
    })
    expect(restricted.body.data.templates[0].matches_msegat_template).toEqual({
      ar: true,
      en: true,
    })
    const unapproved = await call('PUT', '/settings/sms', {
      templates: [
        {
          key: 'otp_code',
          body: {
            ar: 'رمزكم في منصة مؤشر هو {code}.',
            en: 'Your Moashir verification code is {code}. It is valid for {minutes} minutes.',
          },
        },
      ],
    })
    expect(unapproved.body).toMatchObject({
      code: 'sms.template_not_approved',
      errors: { 'templates.otp_code.body.ar': [expect.any(String)] },
    })
  })

  it('validates the template placeholders and never echoes a replaced bearer token', async () => {
    as(SYSTEM_ADMIN)
    const missing = await call('PUT', '/settings/sms', {
      templates: [{ key: 'otp_code', body: { ar: 'رمز التحقق في منصة مؤشر.', en: 'Code {code}' } }],
    })
    expect(missing.status).toBe(422)
    expect(Object.keys(missing.body.errors)).toEqual(['templates.0.body.ar'])
    const replaced = await call('PUT', '/settings/sms', {
      providers: { taqnyat: { bearer_token: FAKE.taqnyatToken } },
    })
    expectNoSecret(replaced.body, FAKE.taqnyatToken)
    expect(replaced.body.data.providers.taqnyat).toMatchObject({
      bearer_token: { last4: '0001' },
      last_test: null,
    })
  })
})

describe('e-mail settings', () => {
  const settingsRow = {
    host: 'smtp.mail.example.sa',
    port: 587,
    encryption: 'starttls',
    username: 'no-reply@moashir.example.sa',
    from_address: 'no-reply@moashir.example.sa',
    from_name: { ar: 'منصة مؤشر', en: 'Moashir Platform' },
    reply_to: null,
    ehlo_domain: null,
    timeout_seconds: 15,
  }

  it('answers the flat GET shape from the environment bootstrap', async () => {
    as(SYSTEM_ADMIN)
    const { data } = (await call('GET', '/settings/email')).body
    expect(keysOf(data)).toEqual(
      sorted([
        'active',
        'mode',
        'source',
        'configured',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'reply_to',
        'ehlo_domain',
        'timeout_seconds',
        'test_address',
        'last_test',
        'checklist',
      ]),
    )
    expect(data).toMatchObject({
      source: 'environment',
      configured: true,
      active: true,
      port: 587,
      encryption: 'starttls',
      password: { set: true, last4: null },
      last_test: null,
    })
    expect(data.checklist.map((item) => item.key)).toEqual(['mailbox', 'spf', 'dkim', 'dmarc'])
  })

  it('moves to settings on the first save and refuses activation without the password', async () => {
    as(SYSTEM_ADMIN)
    const incomplete = await call('PUT', '/settings/email', { ...settingsRow, active: true })
    expect(incomplete.body).toMatchObject({
      code: 'integration.configuration_incomplete',
      errors: { active: [expect.any(String)] },
    })
    expect((await call('GET', '/settings/email')).body.data.source).toBe('environment')
    const saved = await call('PUT', '/settings/email', {
      ...settingsRow,
      active: true,
      password: FAKE.smtpPassword,
    })
    expect(saved.body.data).toMatchObject({
      source: 'settings',
      configured: true,
      active: true,
      password: { set: true, last4: null },
    })
    expectNoSecret(saved.body, FAKE.smtpPassword)
  })

  it('refuses private hosts and requires the test mailbox in test mode', async () => {
    as(SYSTEM_ADMIN)
    const host = await call('PUT', '/settings/email', { host: 'mail.internal' })
    expect(host.body).toMatchObject({
      code: 'email.host_not_allowed',
      errors: { host: [expect.any(String)] },
    })
    const mailbox = await call('PUT', '/settings/email', { mode: 'test' })
    expect(mailbox.body).toMatchObject({
      code: 'email.test_address_required',
      errors: { test_address: [expect.any(String)] },
    })
  })

  it('tests the server with the advisory DNS info and sends a redirected test e-mail', async () => {
    as(SYSTEM_ADMIN)
    const test = await call('POST', '/settings/email/test-connection')
    expect(test.body.data.checks.map((item) => item.key)).toEqual(['host_allowed', 'handshake'])
    expect(test.body.data.info).toEqual({ spf_record_found: true, dmarc_policy: null })
    await call('PUT', '/settings/email', {
      mode: 'test',
      test_address: 'it-team@moashir.example.sa',
    })
    const sent = await call('POST', '/settings/email/test', {
      recipient: 'it-team@moashir.example.sa',
    })
    expect(sent.body.data).toEqual({ sent: true, redirected_to_test_mailbox: true })
  })
})

describe('ai provider settings', () => {
  it('answers the GET shape with both providers and their common fields', async () => {
    as(SYSTEM_ADMIN)
    const { data } = (await call('GET', '/settings/ai')).body
    expect(keysOf(data)).toEqual(
      sorted([
        'active_provider',
        'mode',
        'source',
        'placement',
        'notice',
        'providers',
        'checklist',
      ]),
    )
    const common = [
      'configured',
      'api_key',
      'model',
      'max_output_tokens',
      'timeout_seconds',
      'data_transfer_acknowledgement',
      'last_test',
    ]
    expect(keysOf(data.providers.openai)).toEqual(
      sorted([...common, 'region', 'organization_id', 'project_id']),
    )
    expect(keysOf(data.providers.anthropic)).toEqual(
      sorted([...common, 'workspace_id', 'inference_geo']),
    )
    expect(data.providers.openai).toMatchObject({
      configured: false,
      max_output_tokens: 1024,
      timeout_seconds: 30,
      region: 'global',
      data_transfer_acknowledgement: {
        acknowledged: false,
        acknowledged_at: null,
        acknowledged_by: null,
      },
    })
    expect(data).toMatchObject({
      active_provider: null,
      placement: 'public_only',
      notice: { ar: expect.any(String), en: expect.any(String) },
    })
    expect(data.checklist.map((item) => item.key)).toEqual([
      'provider_account_and_billing',
      'spend_limits',
      'data_retention_contract',
    ])
  })

  it('needs a stored key before loading models', async () => {
    as(SYSTEM_ADMIN)
    expect((await call('GET', '/settings/ai/models?provider=openai')).body).toMatchObject({
      code: 'integration.not_configured',
    })
    await call('PUT', '/settings/ai', { providers: { openai: { api_key: FAKE.aiKey } } })
    const models = await call('GET', '/settings/ai/models?provider=openai')
    expect(models.body.data[0]).toEqual({
      id: 'gpt-4.1',
      display_name: null,
      max_output_tokens: 32768,
    })
    expect(models.body.meta).toEqual({ provider: 'openai' })
  })

  it('activates a provider only with its model and data transfer acknowledgement', async () => {
    as(SYSTEM_ADMIN)
    await call('PUT', '/settings/ai', { providers: { openai: { api_key: FAKE.aiKey } } })
    expect((await call('PUT', '/settings/ai', { active_provider: 'openai' })).body.code).toBe(
      'integration.configuration_incomplete',
    )
    await call('PUT', '/settings/ai', { providers: { openai: { model: 'gpt-4.1-mini' } } })
    const unacknowledged = await call('PUT', '/settings/ai', { active_provider: 'openai' })
    expect(unacknowledged.body).toMatchObject({
      code: 'ai.data_transfer_acknowledgement_required',
      errors: { active_provider: [expect.any(String)] },
    })
    const active = await call('PUT', '/settings/ai', {
      active_provider: 'openai',
      providers: { openai: { data_transfer_acknowledged: true } },
    })
    expect(active.body.data.active_provider).toBe('openai')
    expect(active.body.data.providers.openai.data_transfer_acknowledgement).toMatchObject({
      acknowledged: true,
      acknowledged_by: { id: 2 },
    })
    expectNoSecret(active.body, FAKE.aiKey)
    const withdrawn = await call('PUT', '/settings/ai', {
      providers: { openai: { data_transfer_acknowledged: false } },
    })
    expect(withdrawn.body).toMatchObject({
      code: 'ai.data_transfer_acknowledgement_required',
      errors: { 'providers.openai.data_transfer_acknowledged': [expect.any(String)] },
    })
  })

  it('tests the connection and the answer of the configured provider', async () => {
    as(SYSTEM_ADMIN)
    await call('PUT', '/settings/ai', {
      providers: { anthropic: { api_key: FAKE.aiKey, model: 'claude-haiku-4-5' } },
    })
    const test = await call('POST', '/settings/ai/test-connection', { provider: 'anthropic' })
    expect(test.body.data.checks.map((item) => item.key)).toEqual([
      'api_key_valid',
      'model_available',
    ])
    expect(test.body.data.info).toEqual({ model: 'claude-haiku-4-5', models_count: 2 })
    const answer = await call('POST', '/settings/ai/test-answer', { provider: 'anthropic' })
    expect(answer.body.data).toMatchObject({
      ok: true,
      provider: 'anthropic',
      model: 'claude-haiku-4-5',
      finish: 'complete',
      provider_request_id: expect.any(String),
    })
    expect((await call('POST', '/settings/ai/test-connection', {})).body.code).toBe(
      'integration.not_configured',
    )
  })
})
