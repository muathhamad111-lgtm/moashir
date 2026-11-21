import { ref, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { notificationTextsService } from '@/services/notificationTextsService'
import { useToast } from '@/composables/useToast'

export function useNotificationChannels(code, onUpdated) {
  const { t } = useI18n()
  const toast = useToast()
  const busy = ref(false)

  async function update({ channel, enabled }) {
    busy.value = true
    try {
      const { data } = await notificationTextsService.updateChannels(toValue(code), {
        [channel]: enabled,
      })
      onUpdated(data)
      toast.success(t('settings.notifications.channels.saved'))
    } catch (error) {
      toast.error(error?.normalized?.message || t('settings.notifications.channels.saveFailed'))
    } finally {
      busy.value = false
    }
  }

  return { busy, update }
}
