import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { financeSettingsService } from '@/services/financeSettingsService'
import { logger } from '@/utils/logger'
import { errorCodeOf, financeErrorMessage, firstFieldErrors } from '@/utils/payments/financeErrors'
import {
  changedSettings,
  editableFieldsOf,
  financeSettingsSchema,
  formValuesOf,
} from '@/utils/finance/financeSettingsForm'

const REASON_REQUIRED = 'reason_required'
const DEFAULT_LIMITS = { min: 5, max: 60 }

export function useFinanceSettings() {
  const i18n = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const status = ref('loading')
  const errorMessage = ref('')
  const settings = ref(null)
  const saving = ref(false)
  const reasonOpen = ref(false)
  const pending = ref(null)

  const editable = computed(() => editableFieldsOf(settings.value))
  const limits = computed(() => settings.value?.orderValidityLimits ?? DEFAULT_LIMITS)
  const form = useForm({
    validationSchema: computed(() => financeSettingsSchema(i18n.t, limits.value, editable.value)),
  })

  function apply(data) {
    settings.value = data
    form.resetForm({ values: formValuesOf(data) })
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      apply((await financeSettingsService.get()).data)
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function send(payload) {
    saving.value = true
    logger.breadcrumb('form', 'settings.finance.update', { fields: Object.keys(payload) })
    try {
      apply((await financeSettingsService.update(payload)).data)
      toast.success(i18n.t('finance.settings.saved'))
      return true
    } catch (error) {
      if (errorCodeOf(error) === REASON_REQUIRED && !payload.reason) return askForReason(payload)
      form.setErrors(firstFieldErrors(error))
      toast.error(financeErrorMessage(i18n, error, 'finance.settings.failed'))
      return false
    } finally {
      saving.value = false
    }
  }

  function askForReason(payload) {
    pending.value = payload
    reasonOpen.value = true
    return false
  }

  const submit = form.handleSubmit((values) => {
    const payload = changedSettings(settings.value, values, editable.value)
    if (!Object.keys(payload).length) return toast.info(i18n.t('finance.settings.unchanged'))
    return auth.isSuperAdmin ? askForReason(payload) : send(payload)
  })

  async function confirmReason(reason) {
    const saved = await send({ ...pending.value, reason })
    if (saved) pending.value = null
    return saved
  }

  return {
    status,
    errorMessage,
    settings,
    editable,
    limits,
    form,
    saving,
    reasonOpen,
    load,
    submit,
    confirmReason,
  }
}
