import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { kpisService } from '@/services/kpisService'

function resolve(content, locale) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

export function useKpis({ auto = true } = {}) {
  const { locale } = useI18n()
  const kpis = ref([])
  const status = ref('loading')

  function localized(content) {
    return resolve(content, locale.value)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await kpisService.list()
      kpis.value = data ?? []
      status.value = kpis.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  if (auto) onMounted(load)

  return { kpis, status, load, localized }
}
