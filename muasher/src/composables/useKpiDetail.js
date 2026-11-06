import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { kpisService } from '@/services/kpisService'
import { localizedText } from '@/utils/localizedText'

const NOT_FOUND = 404

export function useKpiDetail() {
  const { locale } = useI18n()
  const kpi = ref(null)
  const status = ref('loading')

  function localized(content) {
    return localizedText(content, locale.value)
  }

  async function load(slug) {
    status.value = 'loading'
    kpi.value = null
    try {
      const { data } = await kpisService.getBySlug(slug)
      kpi.value = data ?? null
      status.value = data ? 'success' : 'not-found'
    } catch (error) {
      status.value = error?.response?.status === NOT_FOUND ? 'not-found' : 'error'
    }
  }

  return { kpi, status, load, localized }
}
