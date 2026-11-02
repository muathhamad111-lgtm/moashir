import { PERMISSIONS as P } from './permissionKeys'
import { ROUTE_NAMES as R } from './routeNames'

export const INTEGRATION_KINDS = Object.freeze({
  PAYMENT: 'payment',
  SMS: 'sms',
  EMAIL: 'email',
  AI: 'ai',
})

export const INTEGRATION_KIND_ORDER = Object.freeze([
  INTEGRATION_KINDS.PAYMENT,
  INTEGRATION_KINDS.SMS,
  INTEGRATION_KINDS.EMAIL,
  INTEGRATION_KINDS.AI,
])

export const INTEGRATION_KIND_PERMISSIONS = Object.freeze({
  [INTEGRATION_KINDS.PAYMENT]: P.SETTINGS_PAYMENT_MANAGE,
  [INTEGRATION_KINDS.SMS]: P.SETTINGS_SMS_MANAGE,
  [INTEGRATION_KINDS.EMAIL]: P.SETTINGS_EMAIL_MANAGE,
  [INTEGRATION_KINDS.AI]: P.AI_PROVIDER_MANAGE,
})

export const INTEGRATION_PERMISSIONS = Object.freeze(Object.values(INTEGRATION_KIND_PERMISSIONS))

export const INTEGRATION_KIND_ROUTES = Object.freeze({
  [INTEGRATION_KINDS.PAYMENT]: R.ADMIN_SETTINGS_PAYMENT,
  [INTEGRATION_KINDS.SMS]: R.ADMIN_SETTINGS_SMS,
  [INTEGRATION_KINDS.EMAIL]: R.ADMIN_SETTINGS_EMAIL,
  [INTEGRATION_KINDS.AI]: R.ADMIN_SETTINGS_AI,
})

export const INTEGRATION_KIND_ICONS = Object.freeze({
  [INTEGRATION_KINDS.PAYMENT]: 'fa-solid fa-credit-card',
  [INTEGRATION_KINDS.SMS]: 'fa-solid fa-comment-sms',
  [INTEGRATION_KINDS.EMAIL]: 'fa-solid fa-envelope',
  [INTEGRATION_KINDS.AI]: 'fa-solid fa-robot',
})

export const INTEGRATION_MODES = Object.freeze({ TEST: 'test', LIVE: 'live' })

export const CREDENTIAL_SOURCES = Object.freeze({
  SETTINGS: 'settings',
  ENVIRONMENT: 'environment',
  NONE: 'none',
})

export const INTEGRATION_STATUSES = Object.freeze({
  NOT_CONFIGURED: 'not_configured',
  CONFIGURED_UNTESTED: 'configured_untested',
  TEST_PASSED: 'test_passed',
  TEST_FAILED: 'test_failed',
  ACTIVE: 'active',
})

export const PROVIDER_ERROR_KINDS = Object.freeze([
  'auth_failed',
  'account_inactive',
  'misconfigured',
  'recipient_invalid',
  'rejected',
  'quota_exhausted',
  'rate_limited',
  'unavailable',
  'timeout',
  'request_too_large',
  'unknown',
])

export const UNKNOWN_PROVIDER_ERROR = 'unknown'

export const PROVIDER_ERROR_CODE = 'provider_error'

export const PAYMENT_METHODS = Object.freeze({ CREDIT_CARD: 'creditcard', APPLE_PAY: 'applepay', STC_PAY: 'stcpay' })
export const CARD_NETWORKS = Object.freeze(['mada', 'visa', 'mastercard', 'amex', 'unionpay'])
export const PAYMENT_LINKED_PATHS = Object.freeze(['accredited', 'bag'])

export const NO_ACTIVE_PROVIDER = 'none'

export const SMS_PROVIDERS = Object.freeze({ TAQNYAT: 'taqnyat', MSEGAT: 'msegat' })
export const TAQNYAT_ACTIVE_SENDER = 'active'

export const SMTP_PORTS = Object.freeze([587, 465, 25, 2525])
export const SMTP_ENCRYPTIONS = Object.freeze(['starttls', 'tls'])

export const AI_PROVIDERS = Object.freeze({ OPENAI: 'openai', ANTHROPIC: 'anthropic' })
export const OPENAI_REGIONS = Object.freeze(['global', 'us', 'eu', 'ae', 'au', 'ca', 'jp', 'in', 'sg', 'kr', 'gb'])
export const ANTHROPIC_INFERENCE_GEOS = Object.freeze(['global', 'us'])
export const AI_PLACEMENTS = Object.freeze(['public_only', 'platform_only', 'public_and_platform'])
export const AI_FINISHES = Object.freeze(['complete', 'truncated', 'refused', 'blocked'])

export const INTEGRATION_LIMITS = Object.freeze({
  TEST_RECIPIENTS_MAX: 5,
  TAQNYAT_SENDER_MAX: 50,
  MSEGAT_SENDER_MAX: 11,
  MSEGAT_USERNAME_MAX: 100,
  APPLE_PAY_LABEL_MAX: 64,
  APPLE_PAY_FILE_MAX_BYTES: 65536,
  SMS_TEMPLATE_MAX_CHARS: 480,
  SMS_SINGLE_SEGMENT_CHARS: 70,
  EMAIL_TIMEOUT: { MIN: 5, MAX: 60 },
  AI_TIMEOUT: { MIN: 5, MAX: 60 },
  AI_MAX_OUTPUT_TOKENS: { MIN: 64, MAX: 4096 },
  AI_NOTICE_MAX: 500,
  AI_IDENTIFIER_MAX: 100,
})

export const SECRET_PATTERNS = Object.freeze({
  NO_WHITESPACE: /^\S+$/,
  PRINTABLE: /^[\x21-\x7E]+$/,
  SAUDI_MOBILE: /^\+9665\d{8}$/,
  SENDER_NAME: /^[A-Za-z0-9]+$/,
  AI_MODEL: /^[A-Za-z0-9._:-]+$/,
  ANTHROPIC_WORKSPACE_PREFIX: 'wrkspc_',
  HOSTNAME: /^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(?:\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/,
})

export const REASON_REQUIRED_CODE = 'reason_required'

export const INTEGRATION_ERROR_CODES = Object.freeze([
  'reason_required',
  'provider_error',
  'provider_unavailable',
  'rate_limited',
  'integration.not_configured',
  'integration.test_required',
  'integration.configuration_incomplete',
  'ai.data_transfer_acknowledgement_required',
  'payment.method_requires_confirmation',
  'payment.confirmation_in_use',
  'payment.webhook_requires_https',
  'payment.apple_pay_file_missing',
  'sms.recipient_not_in_test_numbers',
  'sms.template_not_approved',
  'sms.templates_not_loaded',
  'email.host_not_allowed',
  'email.test_address_required',
])

export const CONNECTION_CHECKS = Object.freeze([
  'connection',
  'key_prefixes',
  'secret_key_valid',
  'intents_available',
  'service_status',
  'credentials_valid',
  'account_usable',
  'sender_active',
  'host_allowed',
  'handshake',
  'api_key_valid',
  'model_available',
])

export const CHECK_DETAILS = Object.freeze(['prefix_mismatch', 'sender_not_active', 'model_not_listed', 'host_not_allowed'])

export const CONNECTION_INFO_KEYS = Object.freeze([
  'accountStatus',
  'balance',
  'accountExpiryDate',
  'senderVerifiedBy',
  'webhookRegistered',
  'publishableKeyCheck',
  'spfRecordFound',
  'dmarcPolicy',
  'model',
  'modelsCount',
])
