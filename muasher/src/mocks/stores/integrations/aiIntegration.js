import { AI_DEFAULTS, AI_MODELS, AI_TEST_ANSWER, EMPTY_SECRET } from '../../fixtures/integrations'
import { integrationReject } from './integrationRejection'
import { aiState } from './integrationsState'
import { applySecretPatch, isSecretTouched, secretMetaResource, secretVerdict } from './integrationSecrets'
import { check, connectionResult, failOnVerdict, representativeTest, settleTest } from './integrationTesting'
import {
  checkBoolean,
  checkChoice,
  checkInteger,
  checkLocalized,
  checkNullableChoice,
  checkReason,
  checkSecret,
  fieldErrors,
  has,
  hasSecretChange,
  requireReason,
} from './integrationValidation'

const PROVIDERS = ['openai', 'anthropic']
const MODES = ['test', 'live']
const REGIONS = ['global', 'us', 'eu', 'ae', 'au', 'ca', 'jp', 'in', 'sg', 'kr', 'gb']
const GEOS = ['global', 'us']
const PLACEMENTS = ['public_only', 'platform_only', 'public_and_platform']
const MODEL_PATTERN = /^[A-Za-z0-9._:-]+$/
const IDENTIFIER_PATTERN = /^\S{1,100}$/
const COMMON_FIELDS = ['model', 'maxOutputTokens', 'timeoutSeconds']
const PUBLIC_FIELDS = {
  openai: ['region', 'organizationId', 'projectId', ...COMMON_FIELDS],
  anthropic: ['workspaceId', 'inferenceGeo', ...COMMON_FIELDS],
}
const LATENCY_MS = { openai: 640, anthropic: 710 }
const REQUEST_IDS = { openai: 'resp_fake_example_0001', anthropic: 'msg_fake_example_0001' }

function emptyRow(provider) {
  const fields = Object.fromEntries(PUBLIC_FIELDS[provider].map((field) => [field, null]))
  return { ...fields, apiKey: { ...EMPTY_SECRET }, acknowledgement: null, lastTest: null, isActive: false }
}

function isConfigured(row) {
  return Boolean(row?.apiKey.set && row.model)
}

function activeProviderOf(state) {
  return PROVIDERS.find((provider) => state.rows[provider]?.isActive) ?? null
}

function aiSource(state) {
  return PROVIDERS.some((provider) => state.rows[provider]) ? 'settings' : 'none'
}

export function aiOverview() {
  const state = aiState()
  const activeProvider = activeProviderOf(state)
  return {
    provider: activeProvider,
    active: activeProvider !== null,
    mode: state.mode,
    source: aiSource(state),
    configured: PROVIDERS.some((provider) => isConfigured(state.rows[provider])),
    lastTest: representativeTest(PROVIDERS.map((provider) => state.rows[provider])),
  }
}

function commonResource(row) {
  const stamp = row?.acknowledgement
  return {
    configured: isConfigured(row),
    apiKey: secretMetaResource(row?.apiKey),
    model: row?.model ?? null,
    maxOutputTokens: row?.maxOutputTokens ?? AI_DEFAULTS.maxOutputTokens,
    timeoutSeconds: row?.timeoutSeconds ?? AI_DEFAULTS.timeoutSeconds,
    dataTransferAcknowledgement: {
      acknowledged: Boolean(stamp),
      acknowledgedAt: stamp?.at ?? null,
      acknowledgedBy: stamp?.by ? { ...stamp.by } : null,
    },
    lastTest: row?.lastTest ? { ...row.lastTest } : null,
  }
}

function checklist() {
  return ['provider_account_and_billing', 'spend_limits', 'data_retention_contract'].map((key) => ({ key, confirmable: false, confirmed: null }))
}

export function aiResource() {
  const state = aiState()
  const { openai, anthropic } = state.rows
  return {
    activeProvider: activeProviderOf(state),
    mode: state.mode,
    source: aiSource(state),
    placement: state.placement,
    notice: { ...state.notice },
    providers: {
      openai: {
        ...commonResource(openai),
        region: openai?.region ?? AI_DEFAULTS.openai.region,
        organizationId: openai?.organizationId ?? null,
        projectId: openai?.projectId ?? null,
      },
      anthropic: {
        ...commonResource(anthropic),
        workspaceId: anthropic?.workspaceId ?? null,
        inferenceGeo: anthropic?.inferenceGeo ?? AI_DEFAULTS.anthropic.inferenceGeo,
      },
    },
    checklist: checklist(),
  }
}

function checkIdentifier(errors, path, value, prefix = '') {
  if (value === null || value === undefined || value === '') return
  if (!IDENTIFIER_PATTERN.test(String(value))) errors.add(path, 'field.invalid')
  else if (prefix && !String(value).startsWith(prefix)) errors.add(path, 'field.prefix', { prefix })
}

function validateProvider(errors, provider, patch) {
  if (!patch) return
  const path = `providers.${provider}`
  checkSecret(errors, `${path}.apiKey`, patch.apiKey, { min: 20, max: 512 })
  if (patch.model && !MODEL_PATTERN.test(patch.model)) errors.add(`${path}.model`, 'field.invalid')
  checkInteger(errors, patch, 'maxOutputTokens', { min: 64, max: 4096, path: `${path}.maxOutputTokens` })
  checkInteger(errors, patch, 'timeoutSeconds', { min: 5, max: 60, path: `${path}.timeoutSeconds` })
  checkBoolean(errors, patch, 'dataTransferAcknowledged', `${path}.dataTransferAcknowledged`)
  if (provider === 'openai') {
    checkChoice(errors, patch, 'region', REGIONS, `${path}.region`)
    checkIdentifier(errors, `${path}.organizationId`, patch.organizationId)
    checkIdentifier(errors, `${path}.projectId`, patch.projectId)
  } else {
    checkIdentifier(errors, `${path}.workspaceId`, patch.workspaceId, 'wrkspc_')
    checkChoice(errors, patch, 'inferenceGeo', GEOS, `${path}.inferenceGeo`)
  }
}

function validateAi(body) {
  const errors = fieldErrors()
  checkNullableChoice(errors, body, 'activeProvider', PROVIDERS)
  checkChoice(errors, body, 'mode', MODES)
  checkChoice(errors, body, 'placement', PLACEMENTS)
  checkLocalized(errors, body, 'notice', { max: 500 })
  PROVIDERS.forEach((provider) => validateProvider(errors, provider, body.providers?.[provider]))
  checkReason(errors, body)
  errors.throwIfAny()
}

function writeProvider(state, provider, patch, actor) {
  const fields = PUBLIC_FIELDS[provider].filter((field) => has(patch, field))
  if (!patch || (!fields.length && !hasSecretChange([patch.apiKey]))) return
  const created = !state.rows[provider]
  const row = state.rows[provider] ?? emptyRow(provider)
  const next = { ...row }
  fields.forEach((field) => {
    next[field] = patch[field] === '' ? null : patch[field]
  })
  const publicChanged = fields.some((field) => next[field] !== row[field])
  const secretChanged = isSecretTouched(row.apiKey, patch.apiKey)
  if (!created && !publicChanged && !secretChanged) return
  next.apiKey = applySecretPatch(row.apiKey, patch.apiKey, actor)
  next.lastTest = created || publicChanged || secretChanged ? null : row.lastTest
  state.rows[provider] = next
}

function writeAcknowledgement(state, provider, patch, actor) {
  if (!has(patch, 'dataTransferAcknowledged')) return
  const row = state.rows[provider] ?? emptyRow(provider)
  state.rows[provider] = row
  const acknowledged = patch.dataTransferAcknowledged === true
  if (!acknowledged && row.isActive) {
    throw integrationReject.field('ai.data_transfer_acknowledgement_required', `providers.${provider}.dataTransferAcknowledged`)
  }
  if (acknowledged === Boolean(row.acknowledgement)) return
  row.acknowledgement = acknowledged ? { at: new Date().toISOString(), by: actor } : null
}

function applyActivation(state, body) {
  if (!has(body, 'activeProvider')) return
  const provider = body.activeProvider
  if (provider !== null) {
    const row = state.rows[provider]
    if (!isConfigured(row)) throw integrationReject.field('integration.configuration_incomplete', 'activeProvider')
    if (!row.acknowledgement) throw integrationReject.field('ai.data_transfer_acknowledgement_required', 'activeProvider')
  }
  PROVIDERS.filter((item) => state.rows[item]).forEach((item) => {
    state.rows[item].isActive = item === provider
  })
}

export function updateAi(body, actor, { superAdmin }) {
  validateAi(body)
  requireReason(body, superAdmin && hasSecretChange(PROVIDERS.map((provider) => body.providers?.[provider]?.apiKey)))
  const draft = structuredClone(aiState())
  PROVIDERS.forEach((provider) => writeProvider(draft, provider, body.providers?.[provider], actor))
  PROVIDERS.forEach((provider) => writeAcknowledgement(draft, provider, body.providers?.[provider], actor))
  if (has(body, 'placement')) draft.placement = body.placement
  if (has(body, 'notice')) draft.notice = { ar: body.notice.ar, en: body.notice.en }
  if (has(body, 'mode')) draft.mode = body.mode
  applyActivation(draft, body)
  Object.assign(aiState(), draft)
  return aiResource()
}

function chosenProvider(provider, { required }) {
  const errors = fieldErrors()
  if (required && !provider) errors.add('provider', 'field.required')
  if (provider && !PROVIDERS.includes(provider)) errors.add('provider', 'field.invalid')
  errors.throwIfAny()
  const chosen = provider || activeProviderOf(aiState())
  if (!chosen) throw integrationReject.notConfigured('ai')
  return chosen
}

function configuredRow(provider) {
  const row = aiState().rows[provider]
  if (!isConfigured(row)) throw integrationReject.notConfigured('ai')
  return row
}

export function listModels(requested) {
  const provider = chosenProvider(requested, { required: true })
  const row = aiState().rows[provider]
  if (!row?.apiKey.set) throw integrationReject.notConfigured('ai')
  failOnVerdict(secretVerdict(row.apiKey))
  return { provider, models: AI_MODELS[provider].map((model) => ({ ...model })) }
}

function aiTestResult(provider, row) {
  const base = { provider, mode: aiState().mode, latencyMs: LATENCY_MS[provider] }
  const verdict = secretVerdict(row.apiKey)
  if (verdict !== 'ok') return connectionResult({ ...base, checks: [check('api_key_valid', false, verdict)], errorKind: verdict })
  const listed = AI_MODELS[provider].some((model) => model.id === row.model)
  return connectionResult({
    ...base,
    checks: [check('api_key_valid', true), check('model_available', listed, listed ? null : 'model_not_listed')],
    info: { model: row.model, modelsCount: AI_MODELS[provider].length },
    errorKind: listed ? null : 'misconfigured',
  })
}

export function testAi(requested, actor) {
  const provider = chosenProvider(requested, { required: false })
  const row = configuredRow(provider)
  return settleTest(row, actor, aiTestResult(provider, row))
}

export function testAnswer(requested) {
  const provider = chosenProvider(requested, { required: false })
  const row = configuredRow(provider)
  failOnVerdict(secretVerdict(row.apiKey))
  return {
    ok: true,
    provider,
    model: row.model,
    finish: AI_TEST_ANSWER.finish,
    text: AI_TEST_ANSWER.text,
    inputTokens: AI_TEST_ANSWER.inputTokens,
    outputTokens: AI_TEST_ANSWER.outputTokens,
    latencyMs: LATENCY_MS[provider],
    providerRequestId: REQUEST_IDS[provider],
  }
}
