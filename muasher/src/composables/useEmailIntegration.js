import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { emailIntegrationService } from '@/services/emailIntegrationService'
import { useIntegrationSettings } from '@/composables/useIntegrationSettings'
import { emailSchema } from '@/utils/integrations/emailSchema'
import { emailFormValues, emailPayload, emailSecretsChanged } from '@/utils/integrations/emailForm'
import { checklistSteps } from '@/utils/integrations/checklist'

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
  const { resource, refresh } = settings

  const dnsInfo = ref(null)

  function onTested(result) {
    if (result?.info) dnsInfo.value = result.info
    refresh()
  }

  const dnsRecords = computed(() => {
    const info = dnsInfo.value
    const checked = info !== null
    return [
      { record: 'spf', checked, ok: info?.spfRecordFound === true, value: null },
      { record: 'dkim', checked: false, ok: false, value: null },
      {
        record: 'dmarc',
        checked,
        ok: Boolean(info?.dmarcPolicy),
        value: info?.dmarcPolicy ?? null,
      },
    ]
  })

  const cardStatus = computed(() => {
    const value = resource.value
    return value
      ? {
          source: value.source,
          configured: value.configured,
          active: value.active,
          mode: value.mode,
          lastTest: value.lastTest,
        }
      : null
  })

  const clientSteps = computed(() =>
    checklistSteps(resource.value?.checklist, (key) => t(`integrations.email.checklist.${key}`)),
  )

  return { ...settings, onTested, dnsRecords, cardStatus, clientSteps }
}
