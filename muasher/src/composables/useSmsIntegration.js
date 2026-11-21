import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { smsIntegrationService } from '@/services/smsIntegrationService'
import { useIntegrationSettings } from '@/composables/useIntegrationSettings'
import { PROVIDER_ACTION_STATES, useProviderAction } from '@/composables/useProviderAction'
import { smsSchema } from '@/utils/integrations/smsSchema'
import {
  smsFormValues,
  smsPayload,
  smsSecretsChanged,
  templateErrorPath,
} from '@/utils/integrations/smsForm'
import { checklistSteps } from '@/utils/integrations/checklist'
import { CREDENTIAL_SOURCES, SMS_PROVIDERS, TAQNYAT_ACTIVE_SENDER } from '@/constants/integrations'

const SECRET_FIELDS = { [SMS_PROVIDERS.TAQNYAT]: 'bearerToken', [SMS_PROVIDERS.MSEGAT]: 'apiKey' }

export function useSmsIntegration() {
  const { t } = useI18n()
  const settings = useIntegrationSettings({
    action: 'smsIntegration.save',
    fetch: () => smsIntegrationService.get(),
    update: (payload) => smsIntegrationService.update(payload),
    toFormValues: smsFormValues,
    toPayload: smsPayload,
    schema: smsSchema(t),
    secretsChanged: smsSecretsChanged,
    errorPath: (path) => templateErrorPath(path, settings.resource.value?.templates ?? []),
  })
  const { resource, form, refresh } = settings

  const senders = useProviderAction(() => smsIntegrationService.listTaqnyatSenders())
  const msegatTemplates = useProviderAction(() => smsIntegrationService.listMsegatTemplates(), {
    onSettled: refresh,
  })

  const activeSenders = computed(() =>
    (senders.result.value ?? []).filter((sender) => sender.status === TAQNYAT_ACTIVE_SENDER),
  )
  const msegat = computed(() => resource.value?.providers?.msegat ?? {})
  const templatesLoaded = computed(
    () =>
      msegatTemplates.state.value === PROVIDER_ACTION_STATES.SUCCESS ||
      Boolean(msegat.value.approvedTemplatesLoadedAt),
  )
  const approvedTemplates = computed(
    () => msegatTemplates.result.value ?? msegat.value.approvedTemplates ?? [],
  )
  const templateRestricted = computed(
    () => form.values.providers?.msegat?.templateRestricted === true,
  )
  const approvedBodies = computed(() =>
    templateRestricted.value && templatesLoaded.value
      ? approvedTemplates.value.map((template) => template.body)
      : null,
  )

  function providerStatus(provider) {
    const value = resource.value
    const config = value?.providers?.[provider]
    const active = value?.activeProvider === provider
    return {
      source: config?.[SECRET_FIELDS[provider]]?.set ? value.source : CREDENTIAL_SOURCES.NONE,
      configured: config?.configured === true,
      active,
      mode: active ? value.mode : '',
      lastTest: config?.lastTest ?? null,
    }
  }

  const generalStatus = computed(() => {
    const value = resource.value
    if (!value) return null
    const active = value.activeProvider ? value.providers[value.activeProvider] : null
    const configured = active
      ? active.configured
      : Object.values(value.providers).some((config) => config.configured)
    return {
      source: value.source,
      configured,
      active: Boolean(active),
      mode: value.mode,
      lastTest: active?.lastTest ?? null,
    }
  })

  const clientSteps = computed(() =>
    checklistSteps(resource.value?.checklist, (key) => t(`integrations.sms.checklist.${key}`)),
  )
  const defaultTestProvider = computed(
    () => resource.value?.activeProvider ?? SMS_PROVIDERS.TAQNYAT,
  )

  return {
    ...settings,
    senders,
    activeSenders,
    msegatTemplates,
    approvedTemplates,
    templatesLoaded,
    templateRestricted,
    approvedBodies,
    providerStatus,
    generalStatus,
    clientSteps,
    defaultTestProvider,
  }
}
