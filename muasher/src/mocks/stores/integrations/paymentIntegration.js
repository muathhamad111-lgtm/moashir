import {
  APPLE_PAY_ASSOCIATION_PATH,
  MOCK_APP_HOST,
  MOCK_EGRESS_IPS,
  MOYASAR_WEBHOOK_PATH,
  emptyPaymentRow,
} from '../../fixtures/integrations'
import { integrationReject } from './integrationRejection'
import { paymentState } from './integrationsState'
import {
  applySecretPatch,
  isSecretTouched,
  secretMetaResource,
  secretVerdict,
} from './integrationSecrets'
import {
  check,
  connectionResult,
  failOnVerdict,
  representativeTest,
  settleTest,
} from './integrationTesting'
import { mockIsProduction } from './mockEnvironment'
import {
  checkBoolean,
  checkChoice,
  checkLocalized,
  checkReason,
  checkSecret,
  checkSubset,
  checkToken,
  fieldErrors,
  has,
  hasSecretChange,
  requireReason,
} from './integrationValidation'

const MODES = ['test', 'live']
const METHODS = ['creditcard', 'applepay', 'stcpay']
const NETWORKS = ['mada', 'visa', 'mastercard', 'amex', 'unionpay']
const LINKED_PATHS = ['accredited', 'bag', 'self', 'award']
const CHECKOUT_PATTERNS = ['intent', 'form']
const SETTING_FIELDS = [
  'checkoutPattern',
  'methods',
  'supportedNetworks',
  'applePayLabel',
  'linkedPaths',
]
const CONFIRMATIONS = ['applePayDomain', 'stcpayActivation']
const METHOD_CONFIRMATIONS = Object.freeze({
  applepay: 'applePayDomain',
  stcpay: 'stcpayActivation',
})
const KEY_LENGTH = { min: 16, max: 256 }
const WEBHOOK_SECRET_LENGTH = { min: 32, max: 128 }
const LABEL_MAX = 64
const TEST_LATENCY_MS = 412

function webhookUrl() {
  return `https://${MOCK_APP_HOST}${MOYASAR_WEBHOOK_PATH}`
}

function isConfigured(row) {
  return Boolean(row?.publishableKey && row.secretKey.set)
}

function isActive(state) {
  return MODES.some((mode) => state.rows[mode]?.isActive === true)
}

function paymentSource(state) {
  return MODES.some((mode) => state.rows[mode]) ? 'settings' : 'none'
}

export function paymentOverview() {
  const state = paymentState()
  const rows = MODES.map((mode) => state.rows[mode])
  return {
    provider: 'moyasar',
    active: isActive(state),
    mode: state.mode,
    source: paymentSource(state),
    configured: rows.some(isConfigured),
    lastTest: representativeTest(rows),
  }
}

export function paymentHidesTestPayments() {
  const state = paymentState()
  return state.mode === 'live' && isConfigured(state.rows.live)
}

export function paymentTestModeInProduction() {
  return mockIsProduction() && paymentState().mode === 'test'
}

function pairResource(state, mode) {
  const row = state.rows[mode]
  return {
    configured: isConfigured(row),
    active: isActive(state) && state.mode === mode,
    publishableKey: row?.publishableKey ?? null,
    secretKey: secretMetaResource(row?.secretKey),
    webhookSecret: secretMetaResource(row?.webhookSecret),
    webhook: row?.webhook ? { ...row.webhook } : null,
    lastTest: row?.lastTest ? { ...row.lastTest } : null,
  }
}

function confirmationResource(stamp) {
  return {
    confirmed: Boolean(stamp),
    confirmedAt: stamp?.at ?? null,
    confirmedBy: stamp?.by ? { ...stamp.by } : null,
  }
}

function associationFileResource(file) {
  if (!file)
    return { set: false, sizeBytes: null, sha256: null, uploadedAt: null, uploadedBy: null }
  return {
    set: true,
    sizeBytes: file.sizeBytes,
    sha256: file.sha256,
    uploadedAt: file.uploadedAt,
    uploadedBy: { ...file.uploadedBy },
  }
}

function valuesToCopy() {
  return {
    webhookUrl: webhookUrl(),
    webhookMethod: 'POST',
    webhookEvents: 'all',
    applePayHost: MOCK_APP_HOST,
    applePayAssociationUrl: `https://${MOCK_APP_HOST}${APPLE_PAY_ASSOCIATION_PATH}`,
    egressIps: [...MOCK_EGRESS_IPS],
  }
}

function checklist(state) {
  return [
    { key: 'live_account_activation', confirmable: false, confirmed: null },
    {
      key: 'apple_pay_domain',
      confirmable: true,
      confirmed: Boolean(state.confirmations.applePayDomain),
    },
    {
      key: 'stcpay_activation',
      confirmable: true,
      confirmed: Boolean(state.confirmations.stcpayActivation),
    },
    { key: 'ip_whitelist', confirmable: false, confirmed: null },
    { key: 'manual_webhook', confirmable: false, confirmed: null },
  ]
}

export function paymentResource() {
  const state = paymentState()
  return {
    provider: 'moyasar',
    mode: state.mode,
    active: isActive(state),
    source: paymentSource(state),
    isTestModeInProduction: paymentTestModeInProduction(),
    checkoutPattern: state.checkoutPattern,
    test: pairResource(state, 'test'),
    live: pairResource(state, 'live'),
    methods: [...state.methods],
    supportedNetworks: [...state.supportedNetworks],
    applePayLabel: { ...state.applePayLabel },
    confirmations: {
      applePayDomain: confirmationResource(state.confirmations.applePayDomain),
      stcpayActivation: confirmationResource(state.confirmations.stcpayActivation),
    },
    linkedPaths: [...state.linkedPaths],
    applePayAssociationFile: associationFileResource(state.associationFile),
    valuesToCopy: valuesToCopy(),
    checklist: checklist(state),
  }
}

function validatePair(errors, body, mode) {
  if (!has(body, mode)) return
  const pair = body[mode] ?? {}
  checkToken(errors, `${mode}.publishableKey`, pair.publishableKey, {
    ...KEY_LENGTH,
    prefix: `pk_${mode}_`,
  })
  checkSecret(errors, `${mode}.secretKey`, pair.secretKey, { ...KEY_LENGTH, prefix: `sk_${mode}_` })
  checkSecret(errors, `${mode}.webhookSecret`, pair.webhookSecret, WEBHOOK_SECRET_LENGTH)
}

function validatePayment(body) {
  const errors = fieldErrors()
  checkChoice(errors, body, 'mode', MODES)
  checkBoolean(errors, body, 'active')
  checkChoice(errors, body, 'checkoutPattern', CHECKOUT_PATTERNS)
  MODES.forEach((mode) => validatePair(errors, body, mode))
  checkSubset(errors, body, 'methods', METHODS)
  checkSubset(errors, body, 'supportedNetworks', NETWORKS, { min: 1 })
  checkLocalized(errors, body, 'applePayLabel', { max: LABEL_MAX })
  CONFIRMATIONS.forEach((key) =>
    checkBoolean(errors, body.confirmations, key, `confirmations.${key}`),
  )
  checkSubset(errors, body, 'linkedPaths', LINKED_PATHS)
  checkReason(errors, body)
  errors.throwIfAny()
}

function touchesSecrets(body) {
  return hasSecretChange(
    MODES.flatMap((mode) => [body[mode]?.secretKey, body[mode]?.webhookSecret]),
  )
}

function writePair(state, mode, patch, actor) {
  if (
    !patch ||
    (!has(patch, 'publishableKey') && !hasSecretChange([patch.secretKey, patch.webhookSecret]))
  )
    return
  const created = !state.rows[mode]
  const row = state.rows[mode] ?? emptyPaymentRow()
  const publishableKey = has(patch, 'publishableKey')
    ? patch.publishableKey || null
    : row.publishableKey
  const publicChanged = publishableKey !== row.publishableKey
  const testedSecretChanged = isSecretTouched(row.secretKey, patch.secretKey)
  if (
    !created &&
    !publicChanged &&
    !testedSecretChanged &&
    !isSecretTouched(row.webhookSecret, patch.webhookSecret)
  )
    return
  state.rows[mode] = {
    ...row,
    publishableKey,
    secretKey: applySecretPatch(row.secretKey, patch.secretKey, actor),
    webhookSecret: applySecretPatch(row.webhookSecret, patch.webhookSecret, actor, {
      showsLastFour: false,
    }),
    lastTest: created || publicChanged || testedSecretChanged ? null : row.lastTest,
  }
}

function writeSettings(state, body) {
  SETTING_FIELDS.filter((field) => has(body, field)).forEach((field) => {
    state[field] = structuredClone(body[field])
  })
}

function writeConfirmations(state, body, actor) {
  CONFIRMATIONS.filter((key) => has(body.confirmations, key)).forEach((key) => {
    const confirmed = body.confirmations[key] === true
    if (confirmed === Boolean(state.confirmations[key])) return
    state.confirmations[key] = confirmed ? { at: new Date().toISOString(), by: actor } : null
  })
}

function assertMethodsConfirmed(state, body) {
  const missing = state.methods
    .map((method) => METHOD_CONFIRMATIONS[method])
    .find((key) => key && !state.confirmations[key])
  if (!missing) return
  throw has(body, 'methods')
    ? integrationReject.field('payment.method_requires_confirmation', 'methods')
    : integrationReject.field('payment.confirmation_in_use', `confirmations.${missing}`)
}

function assertActivatable(row, field) {
  if (!isConfigured(row))
    throw integrationReject.field('integration.configuration_incomplete', field)
  if (!row.lastTest?.ok) throw integrationReject.field('integration.test_required', field)
}

function activate(state, mode) {
  MODES.filter((item) => state.rows[item]).forEach((item) => {
    state.rows[item].isActive = item === mode
  })
}

function applyMode(state, body) {
  if (!has(body, 'mode') || body.mode === state.mode) return
  requireReason(body, mockIsProduction() && body.mode === 'test')
  const wasActive = isActive(state)
  if (wasActive || body.mode === 'live') assertActivatable(state.rows[body.mode], 'mode')
  if (wasActive) activate(state, body.mode)
  state.mode = body.mode
}

function applyActivation(state, body) {
  if (!has(body, 'active')) return
  if (!body.active) {
    activate(state, null)
    return
  }
  assertActivatable(state.rows[state.mode], 'active')
  activate(state, state.mode)
}

export function updatePayment(body, actor, { superAdmin }) {
  validatePayment(body)
  requireReason(body, superAdmin && touchesSecrets(body))
  const state = paymentState()
  const draft = structuredClone(state)
  MODES.forEach((mode) => writePair(draft, mode, body[mode], actor))
  writeSettings(draft, body)
  writeConfirmations(draft, body, actor)
  assertMethodsConfirmed(draft, body)
  applyMode(draft, body)
  applyActivation(draft, body)
  Object.assign(state, draft)
  return paymentResource()
}

function validateMode(body, { required }) {
  const errors = fieldErrors()
  if (required && !has(body, 'mode')) errors.add('mode', 'field.required')
  checkChoice(errors, body, 'mode', MODES)
  checkReason(errors, body)
  errors.throwIfAny()
}

function configuredRow(mode) {
  const row = paymentState().rows[mode]
  if (!isConfigured(row)) throw integrationReject.notConfigured('payment')
  return row
}

function paymentTestResult(row, mode) {
  const base = { provider: 'moyasar', mode, latencyMs: TEST_LATENCY_MS }
  if (!row.publishableKey.startsWith(`pk_${mode}_`)) {
    return connectionResult({
      ...base,
      checks: [check('key_prefixes', false, 'prefix_mismatch')],
      errorKind: 'misconfigured',
    })
  }
  const verdict = secretVerdict(row.secretKey)
  if (verdict !== 'ok') {
    return connectionResult({
      ...base,
      checks: [check('key_prefixes', true), check('secret_key_valid', false, verdict)],
      errorKind: verdict,
    })
  }
  const intents =
    paymentState().checkoutPattern === 'intent' ? [check('intents_available', true)] : []
  return connectionResult({
    ...base,
    checks: [check('key_prefixes', true), check('secret_key_valid', true), ...intents],
    info: {
      webhookRegistered: Boolean(row.webhook),
      publishableKeyCheck: 'validated_at_first_checkout',
    },
  })
}

export function testPayment(body, actor) {
  validateMode(body, { required: false })
  const mode = body.mode ?? paymentState().mode
  const row = configuredRow(mode)
  return settleTest(row, actor, paymentTestResult(row, mode))
}

export function registerWebhook(body, actor, { superAdmin }) {
  validateMode(body, { required: true })
  requireReason(body, superAdmin)
  const row = configuredRow(body.mode)
  failOnVerdict(secretVerdict(row.secretKey))
  const now = new Date().toISOString()
  row.webhook = { id: `wh_${body.mode}_fake_example_0002`, url: webhookUrl(), registeredAt: now }
  row.webhookSecret = { set: true, last4: null, updatedAt: now, updatedBy: actor, verdict: 'ok' }
  return { mode: body.mode, webhookId: row.webhook.id, url: row.webhook.url }
}

export function storeAssociationFile({ sizeBytes, sha256 }, actor) {
  paymentState().associationFile = {
    sizeBytes,
    sha256,
    uploadedAt: new Date().toISOString(),
    uploadedBy: actor,
  }
  return associationFileResource(paymentState().associationFile)
}

function storedFile() {
  const file = paymentState().associationFile
  if (!file) throw integrationReject.missing('payment.apple_pay_file_missing')
  return file
}

export function removeAssociationFile() {
  storedFile()
  paymentState().associationFile = null
}

export function checkAssociationFile() {
  storedFile()
  return { url: valuesToCopy().applePayAssociationUrl, reachable: true, status: 200, matches: true }
}
