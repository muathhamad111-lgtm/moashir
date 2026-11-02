import { AI_PROVIDERS } from '@/constants/integrations'
import { keptSecret, withSecrets } from './secretValue'
import {
  activeProviderChoice,
  activeProviderValue,
  changedValues,
  trimmedOrNull,
  withoutEmptyObjects,
  writesEverything,
} from './partialPayload'

const PROVIDERS = Object.values(AI_PROVIDERS)

function commonValues(config) {
  return {
    apiKey: keptSecret(),
    model: config.model ?? '',
    maxOutputTokens: config.maxOutputTokens ?? '',
    timeoutSeconds: config.timeoutSeconds ?? '',
    dataTransferAcknowledged: config.dataTransferAcknowledgement?.acknowledged === true,
  }
}

export function aiFormValues(resource) {
  const { openai = {}, anthropic = {} } = resource.providers ?? {}
  return {
    activeProvider: activeProviderChoice(resource.activeProvider),
    mode: resource.mode,
    placement: resource.placement,
    notice: { ar: resource.notice?.ar ?? '', en: resource.notice?.en ?? '' },
    providers: {
      openai: {
        ...commonValues(openai),
        region: openai.region ?? 'global',
        organizationId: openai.organizationId ?? '',
        projectId: openai.projectId ?? '',
      },
      anthropic: { ...commonValues(anthropic), workspaceId: anthropic.workspaceId ?? '', inferenceGeo: anthropic.inferenceGeo ?? 'global' },
    },
  }
}

function generalOf(values) {
  return {
    activeProvider: activeProviderValue(values.activeProvider),
    mode: values.mode,
    placement: values.placement,
    notice: { ar: values.notice.ar.trim(), en: values.notice.en.trim() },
  }
}

function commonOf(config) {
  return {
    model: trimmedOrNull(config.model),
    maxOutputTokens: Number(config.maxOutputTokens),
    timeoutSeconds: Number(config.timeoutSeconds),
    dataTransferAcknowledged: config.dataTransferAcknowledged === true,
  }
}

const PROVIDER_FIELDS = {
  [AI_PROVIDERS.OPENAI]: (config) => ({
    ...commonOf(config),
    region: config.region,
    organizationId: trimmedOrNull(config.organizationId),
    projectId: trimmedOrNull(config.projectId),
  }),
  [AI_PROVIDERS.ANTHROPIC]: (config) => ({ ...commonOf(config), workspaceId: trimmedOrNull(config.workspaceId), inferenceGeo: config.inferenceGeo }),
}

function providerPayload(provider, values, initial, full) {
  const fieldsOf = PROVIDER_FIELDS[provider]
  const changed = changedValues(fieldsOf(values.providers[provider]), fieldsOf(initial.providers[provider]), { full })
  return withSecrets(changed, { apiKey: values.providers[provider].apiKey })
}

export function aiPayload(values, resource) {
  const full = writesEverything(resource)
  const initial = aiFormValues(resource)
  const payload = changedValues(generalOf(values), generalOf(initial), { full })
  const providers = withoutEmptyObjects(Object.fromEntries(PROVIDERS.map((provider) => [provider, providerPayload(provider, values, initial, full)])))
  if (Object.keys(providers).length) payload.providers = providers
  return payload
}

export function aiSecretsChanged(payload) {
  return Object.values(payload.providers ?? {}).some((config) => 'apiKey' in config)
}
