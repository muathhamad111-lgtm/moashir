import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/composables/useToast'
import { logger } from '@/utils/logger'
import { payloadShape } from '@/utils/payloadShape'
import { errorCodeOf, integrationErrorKey } from '@/utils/integrations/providerError'
import { CREDENTIAL_SOURCES, REASON_REQUIRED_CODE } from '@/constants/integrations'

export const INTEGRATION_LOAD_STATES = Object.freeze({
  LOADING: 'loading',
  SUCCESS: 'success',
  ERROR: 'error',
  FORBIDDEN: 'forbidden',
})

const FORBIDDEN_STATUS = 403

function firstMessage(messages) {
  return Array.isArray(messages) ? messages[0] : messages
}

export function useIntegrationSettings({
  action,
  fetch,
  update,
  toFormValues,
  toPayload,
  schema,
  secretsChanged,
  errorPath = (path) => path,
}) {
  const { t } = useI18n()
  const auth = useAuthStore()
  const status = ref(INTEGRATION_LOAD_STATES.LOADING)
  const errorMessage = ref('')
  const resource = ref(null)
  const saving = ref(false)
  const reasonOpen = ref(false)
  const pendingPayload = ref(null)
  const form = useForm({ validationSchema: schema })

  function adopt(envelope) {
    resource.value = envelope.data
    form.resetForm({ values: toFormValues(envelope.data) })
  }

  async function load() {
    status.value = INTEGRATION_LOAD_STATES.LOADING
    errorMessage.value = ''
    try {
      adopt(await fetch())
      status.value = INTEGRATION_LOAD_STATES.SUCCESS
    } catch (error) {
      errorMessage.value = error?.normalized?.message || t('common.error')
      status.value =
        error?.response?.status === FORBIDDEN_STATUS
          ? INTEGRATION_LOAD_STATES.FORBIDDEN
          : INTEGRATION_LOAD_STATES.ERROR
    }
  }

  async function refresh() {
    try {
      resource.value = (await fetch()).data
    } catch (error) {
      toast.error(error?.normalized?.message || t('integrations.refreshFailed'))
    }
  }

  function askForReason(payload) {
    pendingPayload.value = payload
    reasonOpen.value = true
  }

  function failureMessage(error) {
    const key = integrationErrorKey(errorCodeOf(error))
    return key ? t(key) : error?.normalized?.message || t('errors.unexpected')
  }

  function fieldMessages(error) {
    const key = integrationErrorKey(errorCodeOf(error))
    return Object.fromEntries(
      Object.entries(error?.normalized?.fieldErrors ?? {}).map(([path, messages]) => [
        errorPath(path),
        key ? t(key) : firstMessage(messages),
      ]),
    )
  }

  function onSaveFailure(error, payload, reason) {
    logger.breadcrumb('form', `${action}.rejected`, {
      fields: payloadShape(payload),
      status: error?.response?.status ?? null,
    })
    if (errorCodeOf(error) === REASON_REQUIRED_CODE && !reason) return askForReason(payload)
    const fields = fieldMessages(error)
    if (Object.keys(fields).length) form.setErrors(fields)
    toast.error(failureMessage(error))
  }

  async function persist(payload, reason = null) {
    if (saving.value) return
    saving.value = true
    logger.breadcrumb('form', action, { fields: payloadShape(payload) })
    try {
      adopt(await update(reason ? { ...payload, reason } : payload))
      toast.success(t('integrations.saved'))
    } catch (error) {
      onSaveFailure(error, payload, reason)
    } finally {
      saving.value = false
    }
  }

  const submit = form.handleSubmit(
    (values) => {
      const payload = toPayload(values, resource.value)
      if (!Object.keys(payload).length) return toast.info(t('integrations.noChanges'))
      if (auth.isSuperAdmin && secretsChanged(payload)) return askForReason(payload)
      return persist(payload)
    },
    () => toast.error(t('integrations.fixFields')),
  )

  async function confirmReason(reason) {
    const payload = pendingPayload.value
    pendingPayload.value = null
    await persist(payload, reason)
  }

  const fromEnvironment = computed(() => resource.value?.source === CREDENTIAL_SOURCES.ENVIRONMENT)

  return {
    status,
    errorMessage,
    resource,
    fromEnvironment,
    form,
    saving,
    reasonOpen,
    load,
    refresh,
    submit,
    confirmReason,
  }
}
