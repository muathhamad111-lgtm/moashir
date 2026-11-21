import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { insightsSettingsService } from '@/services/insightsSettingsService'
import { logger } from '@/utils/logger'
import { isAligned } from '@/utils/insights'
import {
  changedRules,
  fieldErrorsOf,
  formValuesOf,
  insightsSettingsSchema,
} from '@/utils/insightsSettingsForm'

const REASON_REQUIRED = 'reason_required'

export function useInsightsSettings() {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const status = ref('loading')
  const settings = ref(null)
  const saving = ref(false)
  const reasonOpen = ref(false)
  const pending = ref(null)
  const form = useForm({ validationSchema: computed(() => insightsSettingsSchema(t)) })

  const draft = computed(() => ({
    gapThresholds: {
      criticalBelow: form.values.criticalBelow,
      strengthFrom: form.values.strengthFrom,
    },
    maturityBands: {
      developingFrom: form.values.developingFrom,
      matureFrom: form.values.matureFrom,
      leadingFrom: form.values.leadingFrom,
    },
  }))
  const aligned = computed(() => isAligned(draft.value.gapThresholds, draft.value.maturityBands))

  function apply(data) {
    settings.value = data
    form.resetForm({ values: formValuesOf(data) })
  }

  async function load() {
    status.value = 'loading'
    try {
      apply((await insightsSettingsService.get()).data)
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  async function send(payload) {
    saving.value = true
    logger.breadcrumb('form', 'settings.insights.update', { groups: Object.keys(payload) })
    try {
      apply((await insightsSettingsService.update(payload)).data)
      toast.success(t('settings.insights.saved'))
      return true
    } catch (error) {
      if (error?.normalized?.code === REASON_REQUIRED && !payload.reason)
        return askForReason(payload)
      form.setErrors(fieldErrorsOf(error?.normalized?.fieldErrors))
      toast.error(error?.normalized?.message || t('settings.insights.failed'))
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
    const payload = changedRules(settings.value, values)
    if (!Object.keys(payload).length) return toast.info(t('settings.insights.unchanged'))
    return auth.isSuperAdmin ? askForReason(payload) : send(payload)
  })

  async function confirmReason(reason) {
    const saved = await send({ ...pending.value, reason })
    if (saved) pending.value = null
    return saved
  }

  function reset() {
    if (settings.value) form.resetForm({ values: formValuesOf(settings.value) })
  }

  return {
    status,
    settings,
    draft,
    aligned,
    saving,
    reasonOpen,
    load,
    submit,
    reset,
    confirmReason,
  }
}
