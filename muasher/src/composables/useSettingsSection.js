import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'

export function useSettingsSection({ fetcher, saver, savedMessage, saveFailedMessage }) {
  const { t } = useI18n()
  const toast = useToast()

  const data = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')
  const saving = ref(false)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await fetcher()
      data.value = envelope.data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message || t('common.error')
      status.value = 'error'
    }
  }

  async function save(payload) {
    if (saving.value) return false
    saving.value = true
    try {
      const envelope = await saver(payload)
      data.value = envelope.data
      toast.success(t(savedMessage ?? 'settings.toasts.saved'))
      return true
    } catch (error) {
      toast.error(error?.normalized?.message || t(saveFailedMessage ?? 'settings.toasts.saveFailed'))
      return false
    } finally {
      saving.value = false
    }
  }

  return { data, status, errorMessage, saving, load, save }
}
