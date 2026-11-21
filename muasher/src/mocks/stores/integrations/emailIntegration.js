import { EMAIL_DEFAULTS, EMAIL_DNS_SEED, EMPTY_SECRET } from '../../fixtures/integrations'
import { integrationReject } from './integrationRejection'
import { emailState } from './integrationsState'
import {
  applySecretPatch,
  isSecretTouched,
  secretMetaResource,
  secretVerdict,
} from './integrationSecrets'
import { check, connectionResult, failOnVerdict, settleTest } from './integrationTesting'
import {
  checkBoolean,
  checkChoice,
  checkEmail,
  checkHostname,
  checkInteger,
  checkLocalized,
  checkReason,
  checkSecret,
  checkText,
  fieldErrors,
  has,
  hasSecretChange,
  requireReason,
} from './integrationValidation'

const MODES = ['test', 'live']
const PORTS = [25, 465, 587, 2465, 2525, 2587]
const ENCRYPTIONS = ['starttls', 'tls']
const BLOCKED_HOSTS = [
  /^localhost$/i,
  /\.local$/i,
  /\.internal$/i,
  /\.localhost$/i,
  /^\d+(?:\.\d+){3}$/,
]
const PUBLIC_FIELDS = [
  'host',
  'port',
  'encryption',
  'username',
  'fromAddress',
  'fromName',
  'replyTo',
  'ehloDomain',
  'timeoutSeconds',
]
const REQUIRED_FIELDS = ['host', 'username', 'fromAddress', 'fromName']
const LATENCY_MS = 523

function emptyRow() {
  return {
    ...Object.fromEntries(PUBLIC_FIELDS.map((field) => [field, null])),
    password: { ...EMPTY_SECRET },
    lastTest: null,
    isActive: false,
  }
}

function isComplete(config) {
  return Boolean(config && REQUIRED_FIELDS.every((field) => config[field]) && config.password?.set)
}

function credentials(state) {
  return state.row ?? state.environment
}

function isConfigured(state) {
  return state.row ? isComplete(state.row) : Boolean(state.environment)
}

function isActive(state) {
  return state.row ? state.row.isActive : Boolean(state.environment)
}

function emailSource(state) {
  if (state.row) return 'settings'
  return state.environment ? 'environment' : 'none'
}

export function emailOverview() {
  const state = emailState()
  return {
    provider: 'smtp',
    active: isActive(state),
    mode: state.mode,
    source: emailSource(state),
    configured: isConfigured(state),
    lastTest: state.row?.lastTest ? { ...state.row.lastTest } : null,
  }
}

function checklist() {
  return ['mailbox', 'spf', 'dkim', 'dmarc'].map((key) => ({
    key,
    confirmable: false,
    confirmed: null,
  }))
}

export function emailResource() {
  const state = emailState()
  const config = credentials(state) ?? {}
  return {
    active: isActive(state),
    mode: state.mode,
    source: emailSource(state),
    configured: isConfigured(state),
    host: config.host ?? null,
    port: config.port ?? EMAIL_DEFAULTS.port,
    encryption: config.encryption ?? EMAIL_DEFAULTS.encryption,
    username: config.username ?? null,
    password: secretMetaResource(config.password),
    fromAddress: config.fromAddress ?? null,
    fromName: config.fromName ? { ...config.fromName } : null,
    replyTo: config.replyTo ?? null,
    ehloDomain: config.ehloDomain ?? null,
    timeoutSeconds: config.timeoutSeconds ?? EMAIL_DEFAULTS.timeoutSeconds,
    testAddress: state.testAddress,
    lastTest: state.row?.lastTest ? { ...state.row.lastTest } : null,
    checklist: checklist(),
  }
}

function validateEmail(body) {
  const errors = fieldErrors()
  checkBoolean(errors, body, 'active')
  checkChoice(errors, body, 'mode', MODES)
  checkHostname(errors, body, 'host')
  checkInteger(errors, body, 'port', { allowed: PORTS })
  checkChoice(errors, body, 'encryption', ENCRYPTIONS)
  checkText(errors, 'username', body.username, { min: 1, max: 255 })
  checkSecret(errors, 'password', body.password, { min: 1, max: 255, allowWhitespace: true })
  checkEmail(errors, body, 'fromAddress')
  checkLocalized(errors, body, 'fromName', { max: 100 })
  checkEmail(errors, body, 'replyTo')
  checkHostname(errors, body, 'ehloDomain')
  checkInteger(errors, body, 'timeoutSeconds', { min: 5, max: 60 })
  checkEmail(errors, body, 'testAddress')
  checkReason(errors, body)
  errors.throwIfAny()
}

function isHostAllowed(host) {
  return !BLOCKED_HOSTS.some((pattern) => pattern.test(host))
}

function writeRow(state, body, actor) {
  const fields = PUBLIC_FIELDS.filter((field) => has(body, field))
  if (!fields.length && !hasSecretChange([body.password])) return
  const created = !state.row
  const row = state.row ?? emptyRow()
  const next = { ...row }
  fields.forEach((field) => {
    next[field] = body[field] === '' ? null : structuredClone(body[field])
  })
  const publicChanged = fields.some(
    (field) => JSON.stringify(next[field]) !== JSON.stringify(row[field]),
  )
  const secretChanged = isSecretTouched(row.password, body.password)
  if (!created && !publicChanged && !secretChanged) return
  next.password = applySecretPatch(row.password, body.password, actor, { showsLastFour: false })
  next.lastTest = created || publicChanged || secretChanged ? null : row.lastTest
  state.row = next
  state.environment = null
}

function applyActivation(state, body) {
  if (!has(body, 'active')) return
  if (body.active && !isComplete(state.row))
    throw integrationReject.field('integration.configuration_incomplete', 'active')
  if (state.row) state.row.isActive = body.active
}

export function updateEmail(body, actor, { superAdmin }) {
  validateEmail(body)
  if (has(body, 'host') && body.host && !isHostAllowed(body.host))
    throw integrationReject.field('email.host_not_allowed', 'host')
  requireReason(body, superAdmin && hasSecretChange([body.password]))
  const draft = structuredClone(emailState())
  writeRow(draft, body, actor)
  if (has(body, 'testAddress')) draft.testAddress = body.testAddress || null
  if (has(body, 'mode')) draft.mode = body.mode
  if (draft.mode === 'test' && !draft.testAddress)
    throw integrationReject.field('email.test_address_required', 'testAddress')
  applyActivation(draft, body)
  Object.assign(emailState(), draft)
  return emailResource()
}

function configuredCredentials() {
  const state = emailState()
  if (!isConfigured(state)) throw integrationReject.notConfigured('email')
  return credentials(state)
}

function connectionTest(config) {
  const base = {
    provider: 'smtp',
    mode: emailState().mode,
    latencyMs: LATENCY_MS,
    info: { ...EMAIL_DNS_SEED },
  }
  if (!isHostAllowed(config.host))
    return connectionResult({
      ...base,
      checks: [check('host_allowed', false, 'host_not_allowed')],
      errorKind: 'misconfigured',
    })
  const verdict = secretVerdict(config.password)
  if (verdict !== 'ok')
    return connectionResult({
      ...base,
      checks: [check('host_allowed', true), check('handshake', false, verdict)],
      errorKind: verdict,
    })
  return connectionResult({
    ...base,
    checks: [check('host_allowed', true), check('handshake', true)],
  })
}

export function testEmail(actor) {
  const config = configuredCredentials()
  const result = connectionTest(config)
  const recordOn = emailState().row ?? {}
  return settleTest(recordOn, actor, result)
}

export function sendTestEmail(body) {
  const errors = fieldErrors()
  if (!body.recipient) errors.add('recipient', 'field.required')
  checkEmail(errors, body, 'recipient')
  errors.throwIfAny()
  failOnVerdict(secretVerdict(configuredCredentials().password))
  return { sent: true, redirectedToTestMailbox: emailState().mode === 'test' }
}
