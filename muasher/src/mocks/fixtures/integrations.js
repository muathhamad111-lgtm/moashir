export const INTEGRATION_ACTOR = Object.freeze({ id: 2, name: 'منيرة بنت خالد العتيبي' })

export const MOCK_APP_HOST = 'moashir.ttshub.live'

export const MOCK_EGRESS_IPS = Object.freeze(['203.0.113.24'])

export const MOYASAR_WEBHOOK_PATH = '/api/v1/webhooks/moyasar'

export const APPLE_PAY_ASSOCIATION_PATH = '/.well-known/apple-developer-merchantid-domain-association'

const SEED_DATES = {
  keys: '2026-10-28T08:40:00Z',
  webhook: '2026-10-28T08:55:00Z',
  tested: '2026-10-30T09:12:00Z',
  failed: '2026-10-31T07:30:00Z',
}

function stored(last4) {
  return { set: true, last4, updatedAt: SEED_DATES.keys, updatedBy: { ...INTEGRATION_ACTOR }, verdict: 'ok' }
}

function passed(latencyMs) {
  return { at: SEED_DATES.tested, ok: true, errorKind: null, latencyMs, by: { ...INTEGRATION_ACTOR } }
}

export const EMPTY_SECRET = Object.freeze({ set: false, last4: null, updatedAt: null, updatedBy: null, verdict: null })

export const ENVIRONMENT_SECRET = Object.freeze({ set: true, last4: null, updatedAt: null, updatedBy: null, verdict: 'ok' })

export const PAYMENT_DEFAULTS = Object.freeze({
  checkoutPattern: 'intent',
  methods: Object.freeze(['creditcard']),
  supportedNetworks: Object.freeze(['mada', 'visa', 'mastercard']),
  applePayLabel: Object.freeze({ ar: 'منصة مؤشر', en: 'Moashir Platform' }),
  linkedPaths: Object.freeze(['accredited', 'bag']),
})

export function emptyPaymentRow() {
  return {
    publishableKey: null,
    secretKey: { ...EMPTY_SECRET },
    webhookSecret: { ...EMPTY_SECRET },
    webhook: null,
    lastTest: null,
    isActive: false,
  }
}

export function paymentSeed() {
  return {
    mode: 'test',
    checkoutPattern: PAYMENT_DEFAULTS.checkoutPattern,
    methods: [...PAYMENT_DEFAULTS.methods],
    supportedNetworks: [...PAYMENT_DEFAULTS.supportedNetworks],
    applePayLabel: { ...PAYMENT_DEFAULTS.applePayLabel },
    linkedPaths: [...PAYMENT_DEFAULTS.linkedPaths],
    confirmations: { applePayDomain: null, stcpayActivation: null },
    associationFile: null,
    rows: {
      test: {
        publishableKey: 'pk_test_fake_example_moashir01',
        secretKey: stored('ex01'),
        webhookSecret: stored(null),
        webhook: {
          id: 'wh_test_fake_example_0001',
          url: `https://${MOCK_APP_HOST}${MOYASAR_WEBHOOK_PATH}`,
          registeredAt: SEED_DATES.webhook,
        },
        lastTest: passed(412),
        isActive: true,
      },
      live: null,
    },
  }
}

export const SMS_TEMPLATE_DEFINITIONS = Object.freeze([
  {
    key: 'otp_code',
    label: { ar: 'رمز التحقق', en: 'Verification code' },
    defaultBody: {
      ar: 'رمز التحقق في منصة مؤشر هو {code} وهو صالح لمدة {minutes} دقيقة.',
      en: 'Your Moashir verification code is {code}. It is valid for {minutes} minutes.',
    },
    placeholders: ['code', 'minutes'],
    requiredPlaceholders: ['code'],
    singleSegment: true,
  },
])

export const MSEGAT_APPROVED_TEMPLATES = Object.freeze([
  { id: '101', title: 'رمز التحقق', body: 'رمز التحقق في منصة مؤشر هو {{1}} وهو صالح لمدة {{2}} دقيقة.' },
  { id: '102', title: 'Verification code', body: 'Your Moashir verification code is {{1}}. It is valid for {{2}} minutes.' },
  { id: '103', title: 'تذكير بموعد التقديم', body: 'تبقى على انتهاء فترة التقديم في منصة مؤشر {{1}} أيام.' },
])

export const TAQNYAT_SENDERS = Object.freeze([
  { senderName: 'Moashir', status: 'active', destination: 'local' },
  { senderName: 'MoashirInfo', status: 'active', destination: 'international' },
  { senderName: 'MoashirOld', status: 'inactive', destination: 'local' },
])

export function smsSeed() {
  return {
    mode: 'test',
    testRecipients: ['+966500000001', '+966500000002'],
    templates: {},
    rows: {
      taqnyat: { senderName: 'Moashir', bearerToken: stored('rt01'), lastTest: passed(268), isActive: true },
      msegat: {
        username: 'moashir-platform',
        senderName: 'Moashir',
        templateRestricted: false,
        apiKey: stored('ms02'),
        approvedTemplates: [],
        templatesLoadedAt: null,
        lastTest: { at: SEED_DATES.failed, ok: false, errorKind: 'unavailable', latencyMs: 15000, by: { ...INTEGRATION_ACTOR } },
        isActive: false,
      },
    },
  }
}

export const EMAIL_DEFAULTS = Object.freeze({ port: 587, encryption: 'starttls', timeoutSeconds: 15 })

export function emailSeed() {
  return {
    mode: 'live',
    testAddress: null,
    row: null,
    environment: {
      host: 'smtp.mail.example.sa',
      port: 587,
      encryption: 'starttls',
      username: 'no-reply@moashir.example.sa',
      password: { ...ENVIRONMENT_SECRET },
      fromAddress: 'no-reply@moashir.example.sa',
      fromName: { ar: 'منصة مؤشر', en: 'Moashir Platform' },
      replyTo: 'support@moashir.example.sa',
      ehloDomain: null,
      timeoutSeconds: 15,
    },
  }
}

export const EMAIL_DNS_SEED = Object.freeze({ spfRecordFound: true, dmarcPolicy: null })

export const AI_DEFAULTS = Object.freeze({
  maxOutputTokens: 1024,
  timeoutSeconds: 30,
  placement: 'public_only',
  notice: Object.freeze({
    ar: 'يجيب المساعد من قاعدة معرفة المنصة فقط. لا تشارك أي معلومات شخصية.',
    en: 'The assistant answers from the platform knowledge base only. Do not share personal information.',
  }),
  openai: Object.freeze({ region: 'global', organizationId: null, projectId: null }),
  anthropic: Object.freeze({ workspaceId: null, inferenceGeo: 'global' }),
})

export const AI_MODELS = Object.freeze({
  openai: [
    { id: 'gpt-4.1', displayName: null, maxOutputTokens: 32768 },
    { id: 'gpt-4.1-mini', displayName: null, maxOutputTokens: 32768 },
    { id: 'gpt-4o-mini', displayName: null, maxOutputTokens: 16384 },
  ],
  anthropic: [
    { id: 'claude-sonnet-4-5', displayName: 'Claude Sonnet 4.5', maxOutputTokens: 64000 },
    { id: 'claude-haiku-4-5', displayName: 'Claude Haiku 4.5', maxOutputTokens: 64000 },
  ],
})

export function aiSeed() {
  return {
    mode: 'test',
    placement: AI_DEFAULTS.placement,
    notice: { ...AI_DEFAULTS.notice },
    rows: { openai: null, anthropic: null },
  }
}

export const AI_TEST_ANSWER = Object.freeze({ text: 'OK', finish: 'complete', inputTokens: 38, outputTokens: 2 })
