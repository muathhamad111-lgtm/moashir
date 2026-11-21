import {
  EMPTY_SECRET,
  MSEGAT_APPROVED_TEMPLATES,
  SMS_TEMPLATE_DEFINITIONS,
  TAQNYAT_SENDERS,
} from '../../fixtures/integrations'
import { integrationReject } from './integrationRejection'
import { smsState } from './integrationsState'
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
import {
  checkBoolean,
  checkChoice,
  checkNullableChoice,
  checkReason,
  checkSecret,
  checkText,
  fieldErrors,
  has,
  hasSecretChange,
  isSaudiMobile,
  requireReason,
} from './integrationValidation'
import { matchesApprovedTemplate as templateMatches } from '@/utils/integrations/smsTemplates'
import { templateRuleError } from './smsTemplateRules'

const PROVIDERS = ['taqnyat', 'msegat']
const MODES = ['test', 'live']
const SENDER_PATTERN = /^[A-Za-z0-9]{1,11}$/
const MAX_RECIPIENTS = 5
const TEMPLATE_MAX = 480
const LATENCY_MS = { taqnyat: 268, msegat: 341 }
const TAQNYAT_INFO = {
  balance: '1250.00',
  currency: 'SAR',
  accountStatus: 'active',
  accountExpiryDate: '2027-06-30',
}
const MSEGAT_INFO = { balance: '830', senderVerifiedBy: 'test_sms' }
const PUBLIC_FIELDS = {
  taqnyat: ['senderName'],
  msegat: ['username', 'senderName', 'templateRestricted'],
}
const SECRET_FIELDS = { taqnyat: 'bearerToken', msegat: 'apiKey' }

function emptyRow(provider) {
  const base = { senderName: null, lastTest: null, isActive: false }
  if (provider === 'taqnyat') return { ...base, bearerToken: { ...EMPTY_SECRET } }
  return {
    ...base,
    username: null,
    templateRestricted: false,
    apiKey: { ...EMPTY_SECRET },
    approvedTemplates: [],
    templatesLoadedAt: null,
  }
}

function secretOf(row, provider) {
  return row?.[SECRET_FIELDS[provider]]
}

function isConfigured(provider, row) {
  if (!row?.senderName || !secretOf(row, provider)?.set) return false
  return provider === 'taqnyat' || Boolean(row.username)
}

function activeProviderOf(state) {
  return PROVIDERS.find((provider) => state.rows[provider]?.isActive) ?? null
}

function smsSource(state) {
  return PROVIDERS.some((provider) => state.rows[provider]) ? 'settings' : 'none'
}

export function smsOverview() {
  const state = smsState()
  const rows = PROVIDERS.map((provider) => state.rows[provider])
  const activeProvider = activeProviderOf(state)
  return {
    provider: activeProvider,
    active: activeProvider !== null,
    mode: state.mode,
    source: smsSource(state),
    configured: PROVIDERS.some((provider) => isConfigured(provider, state.rows[provider])),
    lastTest: representativeTest(rows),
  }
}

function lastTestOf(row) {
  return row?.lastTest ? { ...row.lastTest } : null
}

function providerResource(state, provider) {
  const row = state.rows[provider]
  if (provider === 'taqnyat') {
    return {
      configured: isConfigured(provider, row),
      bearerToken: secretMetaResource(row?.bearerToken),
      senderName: row?.senderName ?? null,
      lastTest: lastTestOf(row),
    }
  }
  return {
    configured: isConfigured(provider, row),
    username: row?.username ?? null,
    apiKey: secretMetaResource(row?.apiKey),
    senderName: row?.senderName ?? null,
    templateRestricted: row?.templateRestricted === true,
    approvedTemplates: (row?.approvedTemplates ?? []).map((template) => ({ ...template })),
    approvedTemplatesLoadedAt: row?.templatesLoadedAt ?? null,
    lastTest: lastTestOf(row),
  }
}

function templateBody(state, key) {
  const stored = state.templates[key]
  const definition = SMS_TEMPLATE_DEFINITIONS.find((item) => item.key === key)
  return stored ? { ...stored } : { ...definition.defaultBody }
}

function approvedBodies(row) {
  return (row?.approvedTemplates ?? []).map((template) => template.body)
}

function templateResource(state, definition) {
  const body = templateBody(state, definition.key)
  const msegat = state.rows.msegat
  const checked = Boolean(msegat?.templatesLoadedAt)
  return {
    key: definition.key,
    label: { ...definition.label },
    body,
    defaultBody: { ...definition.defaultBody },
    placeholders: [...definition.placeholders],
    requiredPlaceholders: [...definition.requiredPlaceholders],
    singleSegment: definition.singleSegment,
    matchesMsegatTemplate: checked
      ? {
          ar: templateMatches(body.ar, approvedBodies(msegat)),
          en: templateMatches(body.en, approvedBodies(msegat)),
        }
      : null,
  }
}

function checklist() {
  return ['sender_registration', 'api_sending_and_ip', 'msegat_templates', 'balance'].map(
    (key) => ({ key, confirmable: false, confirmed: null }),
  )
}

export function smsResource() {
  const state = smsState()
  return {
    activeProvider: activeProviderOf(state),
    mode: state.mode,
    source: smsSource(state),
    testRecipients: [...state.testRecipients],
    providers: {
      taqnyat: providerResource(state, 'taqnyat'),
      msegat: providerResource(state, 'msegat'),
    },
    templates: SMS_TEMPLATE_DEFINITIONS.map((definition) => templateResource(state, definition)),
    checklist: checklist(),
  }
}

function validateRecipients(errors, body) {
  if (!has(body, 'testRecipients')) return
  const recipients = body.testRecipients
  if (!Array.isArray(recipients) || recipients.length > MAX_RECIPIENTS) {
    errors.add('testRecipients', 'field.invalid')
    return
  }
  recipients.forEach((recipient, index) => {
    if (!isSaudiMobile(recipient) || recipients.indexOf(recipient) !== index)
      errors.add(`testRecipients.${index}`, 'field.saudi_mobile')
  })
}

function validateProviders(errors, providers = {}) {
  checkSecret(errors, 'providers.taqnyat.bearerToken', providers.taqnyat?.bearerToken, {
    min: 20,
    max: 512,
  })
  checkText(errors, 'providers.taqnyat.senderName', providers.taqnyat?.senderName, {
    min: 1,
    max: 50,
  })
  checkText(errors, 'providers.msegat.username', providers.msegat?.username, { min: 1, max: 100 })
  checkSecret(errors, 'providers.msegat.apiKey', providers.msegat?.apiKey, { min: 16, max: 256 })
  const sender = providers.msegat?.senderName
  if (sender && !SENDER_PATTERN.test(sender))
    errors.add('providers.msegat.senderName', 'field.invalid')
  checkBoolean(
    errors,
    providers.msegat,
    'templateRestricted',
    'providers.msegat.templateRestricted',
  )
}

function validateTemplates(errors, body) {
  if (!has(body, 'templates')) return
  if (!Array.isArray(body.templates)) {
    errors.add('templates', 'field.invalid')
    return
  }
  const keys = SMS_TEMPLATE_DEFINITIONS.map((definition) => definition.key)
  body.templates.forEach((template, index) => {
    if (!keys.includes(template.key)) errors.add(`templates.${index}.key`, 'field.invalid')
    for (const locale of ['ar', 'en']) {
      const text = String(template.body?.[locale] ?? '')
      if (!text.trim()) errors.add(`templates.${index}.body.${locale}`, 'field.required')
      else if (text.length > TEMPLATE_MAX)
        errors.add(`templates.${index}.body.${locale}`, 'field.length', {
          min: 1,
          max: TEMPLATE_MAX,
        })
    }
  })
}

function validateTemplateRules(body) {
  const errors = fieldErrors()
  for (const [index, template] of (body.templates ?? []).entries()) {
    const definition = SMS_TEMPLATE_DEFINITIONS.find((item) => item.key === template.key)
    for (const locale of ['ar', 'en']) {
      const error = templateRuleError(definition, template.body[locale])
      if (error) errors.add(`templates.${index}.body.${locale}`, `sms_template.${error}`)
    }
  }
  errors.throwIfAny()
}

function validateSms(body) {
  const errors = fieldErrors()
  checkNullableChoice(errors, body, 'activeProvider', PROVIDERS)
  checkChoice(errors, body, 'mode', MODES)
  validateRecipients(errors, body)
  validateProviders(errors, body.providers)
  validateTemplates(errors, body)
  checkReason(errors, body)
  errors.throwIfAny()
  validateTemplateRules(body)
}

function writeProvider(state, provider, patch, actor) {
  const secretField = SECRET_FIELDS[provider]
  const fields = PUBLIC_FIELDS[provider].filter((field) => has(patch, field))
  if (!patch || (!fields.length && !hasSecretChange([patch[secretField]]))) return
  const created = !state.rows[provider]
  const row = state.rows[provider] ?? emptyRow(provider)
  const next = { ...row }
  fields.forEach((field) => {
    next[field] = field === 'templateRestricted' ? patch[field] === true : patch[field] || null
  })
  const publicChanged = fields.some((field) => next[field] !== row[field])
  const secretChanged = isSecretTouched(row[secretField], patch[secretField])
  if (!created && !publicChanged && !secretChanged) return
  next[secretField] = applySecretPatch(row[secretField], patch[secretField], actor)
  next.lastTest = created || publicChanged || secretChanged ? null : row.lastTest
  state.rows[provider] = next
}

function writeSettings(state, body) {
  if (has(body, 'testRecipients')) state.testRecipients = [...body.testRecipients]
  if (has(body, 'templates')) {
    body.templates.forEach((template) => {
      state.templates[template.key] = { ar: template.body.ar, en: template.body.en }
    })
  }
}

function needsTemplateCheck(body) {
  return (
    has(body, 'templates') ||
    has(body.providers?.msegat, 'templateRestricted') ||
    body.activeProvider === 'msegat'
  )
}

function assertTemplatesApproved(state) {
  const msegat = state.rows.msegat
  if (msegat?.templateRestricted !== true) return
  if (!msegat.templatesLoadedAt)
    throw integrationReject.field('sms.templates_not_loaded', 'templates')
  for (const definition of SMS_TEMPLATE_DEFINITIONS) {
    const body = templateBody(state, definition.key)
    const locale = ['ar', 'en'].find((item) => !templateMatches(body[item], approvedBodies(msegat)))
    if (locale)
      throw integrationReject.field(
        'sms.template_not_approved',
        `templates.${definition.key}.body.${locale}`,
      )
  }
}

function applyActivation(state, body) {
  if (!has(body, 'activeProvider')) return
  const provider = body.activeProvider
  if (provider !== null && !isConfigured(provider, state.rows[provider])) {
    throw integrationReject.field('integration.configuration_incomplete', 'activeProvider')
  }
  PROVIDERS.filter((item) => state.rows[item]).forEach((item) => {
    state.rows[item].isActive = item === provider
  })
}

export function updateSms(body, actor, { superAdmin }) {
  validateSms(body)
  requireReason(
    body,
    superAdmin &&
      hasSecretChange(
        PROVIDERS.map((provider) => body.providers?.[provider]?.[SECRET_FIELDS[provider]]),
      ),
  )
  const draft = structuredClone(smsState())
  PROVIDERS.forEach((provider) => writeProvider(draft, provider, body.providers?.[provider], actor))
  writeSettings(draft, body)
  if (needsTemplateCheck(body)) assertTemplatesApproved(draft)
  if (has(body, 'mode')) draft.mode = body.mode
  applyActivation(draft, body)
  Object.assign(smsState(), draft)
  return smsResource()
}

function providerRow(provider) {
  if (!PROVIDERS.includes(provider)) throw integrationReject.validation({})
  return smsState().rows[provider]
}

function configuredRow(provider) {
  const row = providerRow(provider)
  if (!isConfigured(provider, row)) throw integrationReject.notConfigured('sms')
  return row
}

function keyedRow(provider) {
  const row = providerRow(provider)
  if (!secretOf(row, provider)?.set) throw integrationReject.notConfigured('sms')
  return row
}

function taqnyatResult(row, base) {
  const verdict = secretVerdict(row.bearerToken)
  if (verdict === 'auth_failed')
    return connectionResult({
      ...base,
      checks: [check('service_status', true), check('credentials_valid', false, verdict)],
      errorKind: verdict,
    })
  const checks = [check('service_status', true), check('credentials_valid', true)]
  if (verdict === 'account_inactive') {
    return connectionResult({
      ...base,
      checks: [...checks, check('account_usable', false, verdict)],
      info: { ...TAQNYAT_INFO, accountStatus: 'inactive' },
      errorKind: verdict,
    })
  }
  const senderActive = TAQNYAT_SENDERS.some(
    (sender) => sender.senderName === row.senderName && sender.status === 'active',
  )
  return connectionResult({
    ...base,
    checks: [
      ...checks,
      check('account_usable', true),
      check('sender_active', senderActive, senderActive ? null : 'sender_not_active'),
    ],
    info: { ...TAQNYAT_INFO },
    errorKind: senderActive ? null : 'misconfigured',
  })
}

function msegatResult(row, base) {
  const verdict = secretVerdict(row.apiKey)
  if (verdict !== 'ok')
    return connectionResult({
      ...base,
      checks: [check('credentials_valid', false, verdict)],
      errorKind: verdict,
    })
  return connectionResult({
    ...base,
    checks: [check('credentials_valid', true), check('account_usable', true)],
    info: { ...MSEGAT_INFO },
  })
}

export function testSmsProvider(provider, actor) {
  const row = configuredRow(provider)
  const base = { provider, mode: smsState().mode, latencyMs: LATENCY_MS[provider] }
  return settleTest(
    row,
    actor,
    provider === 'taqnyat' ? taqnyatResult(row, base) : msegatResult(row, base),
  )
}

export function taqnyatSenders() {
  failOnVerdict(secretVerdict(keyedRow('taqnyat').bearerToken))
  return TAQNYAT_SENDERS.map((sender) => ({ ...sender }))
}

export function msegatTemplates() {
  const row = keyedRow('msegat')
  failOnVerdict(secretVerdict(row.apiKey))
  row.approvedTemplates = MSEGAT_APPROVED_TEMPLATES.map((template) => ({ ...template }))
  row.templatesLoadedAt = new Date().toISOString()
  return row.approvedTemplates.map((template) => ({ ...template }))
}

export function sendTestSms(body) {
  const errors = fieldErrors()
  if (!isSaudiMobile(body.recipient)) errors.add('recipient', 'field.saudi_mobile')
  checkNullableChoice(errors, body, 'provider', PROVIDERS)
  errors.throwIfAny()
  const state = smsState()
  const provider = body.provider ?? activeProviderOf(state)
  if (!provider) throw integrationReject.notConfigured('sms')
  const row = configuredRow(provider)
  if (state.mode === 'test' && !state.testRecipients.includes(body.recipient)) {
    throw integrationReject.field('sms.recipient_not_in_test_numbers', 'recipient')
  }
  failOnVerdict(secretVerdict(secretOf(row, provider)))
  return { provider, status: 'sent', providerMessageId: `${provider}-fake-example-0001` }
}
