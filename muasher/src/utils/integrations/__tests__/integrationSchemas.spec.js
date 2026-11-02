import { describe, expect, it } from 'vitest'
import { paymentSchema } from '@/utils/integrations/paymentSchema'
import { smsSchema } from '@/utils/integrations/smsSchema'
import { emailSchema } from '@/utils/integrations/emailSchema'
import { aiSchema } from '@/utils/integrations/aiSchema'
import { paymentFormValues, paymentPayload, paymentSecretsChanged, isLiveReady } from '@/utils/integrations/paymentForm'
import { smsFormValues, smsPayload, smsSecretsChanged, templateErrorPath } from '@/utils/integrations/smsForm'
import { emailFormValues, emailPayload } from '@/utils/integrations/emailForm'
import { aiFormValues, aiPayload, aiSecretsChanged } from '@/utils/integrations/aiForm'
import { SECRET_ACTIONS } from '@/utils/integrations/secretValue'

const t = (key, params) => (params ? `${key}:${JSON.stringify(params)}` : key)

async function errorsOf(schema, values) {
  try {
    await schema.validate(values, { abortEarly: false })
    return []
  } catch (failure) {
    return failure.inner.map((item) => item.path)
  }
}

const replace = (value) => ({ action: SECRET_ACTIONS.REPLACE, value })
const clear = () => ({ action: SECRET_ACTIONS.CLEAR, value: '' })

const paymentResource = {
  mode: 'test',
  active: true,
  source: 'settings',
  test: { configured: true, publishableKey: 'pk_test_fake_example_moashir01', secretKey: { set: true }, lastTest: { ok: true } },
  live: { configured: false, publishableKey: null, secretKey: { set: false }, lastTest: null },
  methods: ['creditcard'],
  supportedNetworks: ['mada'],
  applePayLabel: { ar: 'منصة مؤشر', en: 'Moashir Platform' },
  confirmations: { applePayDomain: { confirmed: false }, stcpayActivation: { confirmed: false } },
  linkedPaths: ['accredited'],
}

describe('payment schema and payload', () => {
  it('accepts the stored form and sends nothing when nothing changed', async () => {
    const values = paymentFormValues(paymentResource)
    expect(await errorsOf(paymentSchema(t), values)).toEqual([])
    expect(paymentPayload(values, paymentResource)).toEqual({})
  })

  it('sends only the changed fields, the touched pair and the changed confirmation', () => {
    const values = paymentFormValues(paymentResource)
    values.mode = 'live'
    values.live.publishableKey = 'pk_live_fake_example_moashir02'
    values.live.secretKey = replace('sk_live_fake_example_replacement_5432')
    values.confirmations.applePayDomain = true
    const payload = paymentPayload(values, paymentResource)
    expect(payload).toEqual({
      mode: 'live',
      confirmations: { applePayDomain: true },
      live: { publishableKey: 'pk_live_fake_example_moashir02', secretKey: 'sk_live_fake_example_replacement_5432' },
    })
    expect(paymentSecretsChanged(payload)).toBe(true)
  })

  it('writes every field when the values come from the environment bootstrap', () => {
    const resource = { ...paymentResource, source: 'environment' }
    const payload = paymentPayload(paymentFormValues(resource), resource)
    expect(Object.keys(payload)).toEqual(expect.arrayContaining(['mode', 'active', 'methods', 'supportedNetworks', 'applePayLabel', 'linkedPaths', 'confirmations', 'test', 'live']))
    expect(payload.test).toEqual({ publishableKey: 'pk_test_fake_example_moashir01' })
  })

  it('checks prefixes per mode, the webhook secret length, the Apple Pay confirmation and the label', async () => {
    const values = paymentFormValues(paymentResource)
    values.live.publishableKey = 'pk_test_wrong_prefix_value'
    values.live.secretKey = replace('sk_test_wrong_prefix_value_1')
    values.test.webhookSecret = replace('short')
    values.methods = ['creditcard', 'applepay']
    values.supportedNetworks = []
    values.applePayLabel.en = ''
    expect(await errorsOf(paymentSchema(t), values)).toEqual(
      expect.arrayContaining(['live.publishableKey', 'live.secretKey', 'test.webhookSecret', 'methods', 'supportedNetworks', 'applePayLabel.en']),
    )
  })

  it('knows when the live environment is ready from configured and the live test', () => {
    expect(isLiveReady(paymentResource)).toBe(false)
    expect(isLiveReady({ live: { configured: true, lastTest: { ok: true } } })).toBe(true)
    expect(isLiveReady({ live: { configured: true, lastTest: { ok: false } } })).toBe(false)
  })
})

describe('sms schema and payload', () => {
  const resource = {
    activeProvider: 'msegat',
    mode: 'test',
    source: 'settings',
    testRecipients: ['+966500000001'],
    providers: { taqnyat: { senderName: 'Moashir' }, msegat: { username: '', senderName: 'Moashir Platform', templateRestricted: false } },
    templates: [{ key: 'otp_code', body: { ar: 'رمز التحقق {code}', en: 'Code {code}' } }],
  }

  it('requires the active provider fields and validates Saudi numbers and sender names', async () => {
    const values = smsFormValues(resource)
    values.testRecipients = ['+201000000001']
    values.templates[0].body.en = ''
    expect(await errorsOf(smsSchema(t), values)).toEqual(
      expect.arrayContaining(['testRecipients[0]', 'providers.msegat.username', 'providers.msegat.senderName', 'templates[0].body.en']),
    )
  })

  it('maps no active provider to null and sends only changed providers and templates', () => {
    const values = smsFormValues({ ...resource, activeProvider: null })
    expect(values.activeProvider).toBe('none')
    values.activeProvider = 'taqnyat'
    values.providers.taqnyat.bearerToken = replace('taqnyat_fake_example_bearer_token_0001')
    values.templates[0].body.ar = 'رمز التحقق في منصة مؤشر هو {code}'
    const payload = smsPayload(values, { ...resource, activeProvider: null })
    expect(payload).toEqual({
      activeProvider: 'taqnyat',
      providers: { taqnyat: { bearerToken: 'taqnyat_fake_example_bearer_token_0001' } },
      templates: [{ key: 'otp_code', body: { ar: 'رمز التحقق في منصة مؤشر هو {code}', en: 'Code {code}' } }],
    })
    expect(smsSecretsChanged(payload)).toBe(true)
    values.activeProvider = 'none'
    expect(smsPayload(values, resource).activeProvider).toBeNull()
  })

  it('places a template error keyed by the template key on its form field', () => {
    expect(templateErrorPath('templates.otpCode.body.ar', resource.templates)).toBe('templates.0.body.ar')
    expect(templateErrorPath('templates.otp_code.body.en', resource.templates)).toBe('templates.0.body.en')
    expect(templateErrorPath('templates.0.body.ar', resource.templates)).toBe('templates.0.body.ar')
    expect(templateErrorPath('providers.msegat.senderName', resource.templates)).toBe('providers.msegat.senderName')
  })
})

describe('email schema and payload', () => {
  const resource = {
    active: true,
    mode: 'test',
    source: 'settings',
    host: 'smtp.mail.example.sa',
    port: 587,
    encryption: 'starttls',
    username: 'relay',
    password: { set: true },
    fromAddress: 'no-reply@moashir.example.sa',
    fromName: { ar: 'منصة مؤشر', en: 'Moashir' },
    replyTo: null,
    ehloDomain: null,
    timeoutSeconds: 15,
    testAddress: null,
  }

  it('requires the test mailbox in test mode, a host name and the password when asked', async () => {
    const values = emailFormValues(resource)
    values.host = 'not a host'
    expect(await errorsOf(emailSchema(t), values)).toEqual(expect.arrayContaining(['host', 'testAddress']))
    expect(await errorsOf(emailSchema(t, { passwordRequired: () => true }), emailFormValues(resource))).toContain('password')
  })

  it('sends flat changed fields only, with numbers cast and empty optionals as null', () => {
    const values = { ...emailFormValues(resource), testAddress: 'it-team@moashir.example.sa', port: '465', password: replace('fake example mailbox passphrase') }
    expect(emailPayload(values, resource)).toEqual({ port: 465, testAddress: 'it-team@moashir.example.sa', password: 'fake example mailbox passphrase' })
    const full = emailPayload(emailFormValues({ ...resource, source: 'environment' }), { ...resource, source: 'environment' })
    expect(full).toMatchObject({ host: 'smtp.mail.example.sa', port: 587, replyTo: null, ehloDomain: null, timeoutSeconds: 15, active: true })
    expect(full).not.toHaveProperty('smtp')
  })
})

describe('ai schema and payload', () => {
  const provider = (overrides = {}) => ({
    configured: false,
    apiKey: { set: false },
    model: null,
    maxOutputTokens: 1024,
    timeoutSeconds: 30,
    dataTransferAcknowledgement: { acknowledged: false },
    ...overrides,
  })
  const resource = {
    activeProvider: null,
    mode: 'test',
    source: 'none',
    placement: 'public_only',
    notice: { ar: 'يجيب المساعد من قاعدة المعرفة فقط.', en: 'The assistant answers from the knowledge base only.' },
    providers: {
      openai: provider({ region: 'global', organizationId: null, projectId: null }),
      anthropic: provider({ workspaceId: null, inferenceGeo: 'global' }),
    },
  }

  it('requires the acknowledgement of the provider to activate and the workspace prefix', async () => {
    const values = aiFormValues(resource)
    values.activeProvider = 'anthropic'
    values.providers.anthropic.workspaceId = 'workspace-1'
    values.notice.en = ''
    expect(await errorsOf(aiSchema(t), values)).toEqual(expect.arrayContaining(['activeProvider', 'providers.anthropic.workspaceId', 'notice.en']))
    values.providers.anthropic.dataTransferAcknowledged = true
    expect(await errorsOf(aiSchema(t), values)).not.toContain('activeProvider')
  })

  it('validates tokens, timeouts and the model identifier per provider', async () => {
    const values = aiFormValues(resource)
    values.providers.openai.maxOutputTokens = '9000'
    values.providers.anthropic.timeoutSeconds = '2'
    values.providers.openai.model = 'gpt 4'
    expect(await errorsOf(aiSchema(t), values)).toEqual(
      expect.arrayContaining(['providers.openai.maxOutputTokens', 'providers.anthropic.timeoutSeconds', 'providers.openai.model']),
    )
  })

  it('sends per-provider changes only and the key only when it is replaced or cleared', () => {
    const values = aiFormValues(resource)
    expect(aiPayload(values, resource)).toEqual({})
    values.activeProvider = 'openai'
    values.providers.openai.apiKey = replace('openai_fake_example_project_key_0001')
    values.providers.openai.model = 'gpt-4.1-mini'
    values.providers.openai.dataTransferAcknowledged = true
    values.providers.anthropic.apiKey = clear()
    const payload = aiPayload(values, resource)
    expect(payload).toEqual({
      activeProvider: 'openai',
      providers: {
        openai: { model: 'gpt-4.1-mini', dataTransferAcknowledged: true, apiKey: 'openai_fake_example_project_key_0001' },
        anthropic: { apiKey: { clear: true } },
      },
    })
    expect(aiSecretsChanged(payload)).toBe(true)
  })
})
