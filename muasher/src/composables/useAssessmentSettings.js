import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { assessmentSettingsService } from '@/services/assessmentSettingsService'
import { logger } from '@/utils/logger'
import { firstFieldErrors } from '@/utils/payments/financeErrors'
import {
  assessmentSettingsSchema,
  changedAssessmentSettings,
  settingsFormValues,
} from '@/utils/accredited/assessmentSettingsForm'

const REASON_REQUIRED = 'reason_required'
const DEFAULT_LIMITS = { min: 1, max: 120 }

export function useAssessmentSettings() {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const status = ref('loading')
  const errorMessage = ref('')
  const settings = ref(null)
  const saving = ref(false)
  const reasonOpen = ref(false)
  const pending = ref(null)

  const limits = computed(() => settings.value?.accreditedReviewSlaLimits ?? DEFAULT_LIMITS)
  const form = useForm({
    validationSchema: computed(() => assessmentSettingsSchema(t, limits.value)),
  })

  function apply(data) {
    settings.value = data
    form.resetForm({ values: settingsFormValues(data) })
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      apply((await assessmentSettingsService.get()).data)
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === 403 ? 'forbidden' : 'error'
    }
  }

  function askForReason(payload) {
    pending.value = payload
    reasonOpen.value = true
    return false
  }

  async function send(payload) {
    saving.value = true
    logger.breadcrumb('form', 'settings.assessment.update', { fields: Object.keys(payload) })
    try {
      apply((await assessmentSettingsService.update(payload)).data)
      toast.success(t('settings.assessment.saved'))
      return true
    } catch (error) {
      if (error?.normalized?.code === REASON_REQUIRED && !payload.reason)
        return askForReason(payload)
      form.setErrors(firstFieldErrors(error))
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      return false
    } finally {
      saving.value = false
    }
  }

  const submit = form.handleSubmit((values) => {
    const payload = changedAssessmentSettings(settings.value, values)
    if (!Object.keys(payload).length) return toast.info(t('settings.assessment.unchanged'))
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
    limits,
    form,
    saving,
    reasonOpen,
    load,
    submit,
    confirmReason,
  }
}
