import { ref } from 'vue'
import { pricesSettingsService } from '@/services/pricesSettingsService'

const FORBIDDEN = 403

export function usePathPrices() {
  const status = ref('loading')
  const errorMessage = ref('')
  const settings = ref(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      settings.value = (await pricesSettingsService.get()).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === FORBIDDEN ? 'forbidden' : 'error'
    }
  }

  function replaceRow(row) {
    if (!settings.value) return
    settings.value = {
      ...settings.value,
      rows: settings.value.rows.map((current) => (current.key === row.key ? row : current)),
    }
  }

  return { status, errorMessage, settings, load, replaceRow }
}
