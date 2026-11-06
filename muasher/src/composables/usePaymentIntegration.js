import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { paymentIntegrationService } from '@/services/paymentIntegrationService'
import { useIntegrationSettings } from '@/composables/useIntegrationSettings'
import { useProviderAction } from '@/composables/useProviderAction'
import { useAuthStore } from '@/stores/auth'
import { paymentSchema } from '@/utils/integrations/paymentSchema'
import {
  isLiveReady,
  paymentFormValues,
  paymentPayload,
  paymentSecretsChanged,
} from '@/utils/integrations/paymentForm'
import { checklistSteps } from '@/utils/integrations/checklist'
import { REASON_REQUIRED_CODE } from '@/constants/integrations'

function copyItemsOf(values, t) {
  return [
    {
      key: 'webhookUrl',
      label: t('integrations.payment.copy.webhookUrl'),
      value: values.webhookUrl,
    },
    {
      key: 'webhookMethod',
      label: t('integrations.payment.copy.webhookMethod'),
      value: values.webhookMethod,
    },
    {
      key: 'webhookEvents',
      label: t('integrations.payment.copy.webhookEvents'),
      value: t(`integrations.payment.copy.events.${values.webhookEvents}`),
    },
    {
      key: 'applePayHost',
      label: t('integrations.payment.copy.applePayHost'),
      value: values.applePayHost,
    },
    {
      key: 'applePayAssociationUrl',
      label: t('integrations.payment.copy.applePayAssociationUrl'),
      value: values.applePayAssociationUrl,
    },
    ...(values.egressIps ?? []).map((ip, index) => ({
      key: `egressIp${index}`,
      label: t('integrations.payment.copy.egressIp'),
      value: ip,
    })),
  ]
}

function useWebhookRegistration(refresh) {
  const auth = useAuthStore()
  const action = useProviderAction(
    (mode, reason) => paymentIntegrationService.registerWebhook(mode, reason),
    { onSettled: refresh },
  )
  const mode = ref(null)
  const reasonOpen = ref(false)

  function askForReason(nextMode) {
    action.reset()
    mode.value = nextMode
    reasonOpen.value = true
  }

  async function register(nextMode, reason = null) {
    if (auth.isSuperAdmin && !reason) return askForReason(nextMode)
    mode.value = nextMode
    await action.execute(nextMode, reason)
    if (action.failure.value?.code === REASON_REQUIRED_CODE && !reason) askForReason(nextMode)
  }

  const confirmReason = (reason) => register(mode.value, reason)

  return { action, mode, reasonOpen, register, confirmReason }
}

export function usePaymentIntegration() {
  const { t } = useI18n()
  const settings = useIntegrationSettings({
    action: 'paymentIntegration.save',
    fetch: () => paymentIntegrationService.get(),
    update: (payload) => paymentIntegrationService.update(payload),
    toFormValues: paymentFormValues,
    toPayload: paymentPayload,
    schema: paymentSchema(t),
    secretsChanged: paymentSecretsChanged,
  })
  const { refresh, resource } = settings

  const webhook = useWebhookRegistration(refresh)
  const fileUpload = useProviderAction(
    (file) => paymentIntegrationService.uploadAssociationFile(file),
    { onSettled: refresh },
  )
  const fileRemoval = useProviderAction(() => paymentIntegrationService.removeAssociationFile(), {
    onSettled: refresh,
  })
  const fileCheck = useProviderAction(() => paymentIntegrationService.checkAssociationFile())

  const liveReady = computed(() => isLiveReady(resource.value))
  const testModeBanner = computed(() => resource.value?.isTestModeInProduction === true)
  const copyItems = computed(() =>
    resource.value?.valuesToCopy ? copyItemsOf(resource.value.valuesToCopy, t) : [],
  )
  const clientSteps = computed(() =>
    checklistSteps(
      (resource.value?.checklist ?? []).filter((item) => !item.confirmable),
      (key) => t(`integrations.payment.checklist.${key}`),
    ),
  )
  const cardStatus = computed(() => {
    const value = resource.value
    if (!value) return null
    const pair = value[value.mode]
    return {
      source: value.source,
      configured: pair?.configured === true,
      active: value.active,
      mode: value.mode,
      lastTest: pair?.lastTest ?? null,
    }
  })

  return {
    ...settings,
    webhook,
    fileUpload,
    fileRemoval,
    fileCheck,
    liveReady,
    testModeBanner,
    copyItems,
    clientSteps,
    cardStatus,
  }
}
