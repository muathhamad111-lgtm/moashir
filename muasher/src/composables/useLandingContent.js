import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { contentService } from '@/services/contentService'

function resolve(content, locale) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

export function useLandingContent({ auto = true } = {}) {
  const { locale } = useI18n()
  const content = ref(null)
  const status = ref('loading')

  function localized(value) {
    return resolve(value, locale.value)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await contentService.getLanding()
      const available = Boolean(data) && data.published !== false
      content.value = available ? data : null
      status.value = available ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  if (auto) onMounted(load)

  return { content, status, load, localized }
}
