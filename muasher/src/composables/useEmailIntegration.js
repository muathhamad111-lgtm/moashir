import { useI18n } from 'vue-i18n'
import { emailIntegrationService } from '@/services/emailIntegrationService'
import { useIntegrationSettings } from '@/composables/useIntegrationSettings'
import { emailSchema } from '@/utils/integrations/emailSchema'
import { emailFormValues, emailPayload, emailSecretsChanged } from '@/utils/integrations/emailForm'

export function useEmailIntegration() {
  const { t } = useI18n()
  const settings = useIntegrationSettings({
    action: 'emailIntegration.save',
    fetch: () => emailIntegrationService.get(),
    update: (payload) => emailIntegrationService.update(payload),
    toFormValues: emailFormValues,
    toPayload: emailPayload,
    schema: emailSchema(t, { passwordRequired: () => settings.fromEnvironment.value }),
    secretsChanged: emailSecretsChanged,
  })
  const { refresh } = settings

  function onTested() {
    refresh()
  }

  return { ...settings, onTested }
}
