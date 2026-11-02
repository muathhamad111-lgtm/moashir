import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { kpisService } from '@/services/kpisService'

function resolve(content, locale) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

export function useKpiDetail() {
  const { locale } = useI18n()
  const kpi = ref(null)
  const status = ref('loading')

  function localized(content) {
    return resolve(content, locale.value)
  }

  async function load(slug) {
    status.value = 'loading'
    kpi.value = null
    try {
      const { data } = await kpisService.getBySlug(slug)
      if (!data) {
        status.value = 'not-found'
        return
      }
      kpi.value = data
      status.value = 'success'
    } catch (error) {
      const code = error?.response?.status ?? error?.normalized?.code
      status.value = code === 404 ? 'not-found' : 'error'
    }
  }

  return { kpi, status, load, localized }
}
