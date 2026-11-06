import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { aiIntegrationService } from '@/services/aiIntegrationService'
import { useIntegrationSettings } from '@/composables/useIntegrationSettings'
import { aiSchema } from '@/utils/integrations/aiSchema'
import { aiFormValues, aiPayload, aiSecretsChanged } from '@/utils/integrations/aiForm'
import { checklistSteps } from '@/utils/integrations/checklist'
import { CREDENTIAL_SOURCES } from '@/constants/integrations'

export function useAiIntegration() {
  const { t } = useI18n()
  const settings = useIntegrationSettings({
    action: 'aiIntegration.save',
    fetch: () => aiIntegrationService.get(),
    update: (payload) => aiIntegrationService.update(payload),
    toFormValues: aiFormValues,
    toPayload: aiPayload,
    schema: aiSchema(t),
    secretsChanged: aiSecretsChanged,
  })
  const { resource } = settings

  function providerStatus(provider) {
    const value = resource.value
    const config = value?.providers?.[provider]
    const active = value?.activeProvider === provider
    return {
      source: config?.apiKey?.set ? value.source : CREDENTIAL_SOURCES.NONE,
      configured: config?.configured === true,
      active,
      mode: active ? value.mode : '',
      lastTest: config?.lastTest ?? null,
    }
  }

  const cardStatus = computed(() => {
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
    checklistSteps(resource.value?.checklist, (key) => t(`integrations.ai.checklist.${key}`)),
  )

  return { ...settings, providerStatus, cardStatus, clientSteps }
}
