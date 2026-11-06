import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'
import { firstFieldErrors } from '@/utils/payments/financeErrors'

const REASON_REQUIRED = 'reason_required'
const FORBIDDEN = 403

export function useSettingsEditor({ read, write, schema, valuesOf, changesOf, messages, action }) {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const status = ref('loading')
  const errorMessage = ref('')
  const settings = ref(null)
  const saving = ref(false)
  const reasonOpen = ref(false)
  const pending = ref(null)
  const form = useForm({ validationSchema: computed(() => schema(t, settings.value)) })

  function apply(data) {
    settings.value = data
    form.resetForm({ values: valuesOf(data) })
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      apply((await read()).data)
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === FORBIDDEN ? 'forbidden' : 'error'
    }
  }

  function askForReason(payload) {
    pending.value = payload
    reasonOpen.value = true
    return false
  }

  async function send(payload) {
    saving.value = true
    logger.breadcrumb('form', action, { fields: Object.keys(payload) })
    try {
      apply((await write(payload)).data)
      toast.success(t(messages.saved))
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
    const payload = changesOf(settings.value, values)
    if (!Object.keys(payload).length) return toast.info(t(messages.unchanged))
    return auth.isSuperAdmin ? askForReason(payload) : send(payload)
  })

  async function confirmReason(reason) {
    const saved = await send({ ...pending.value, reason })
    if (saved) pending.value = null
    return saved
  }

  return { status, errorMessage, settings, form, saving, reasonOpen, load, submit, confirmReason }
}
