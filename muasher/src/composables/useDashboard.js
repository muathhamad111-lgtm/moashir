import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { dashboardService } from '@/services/dashboardService'

function resolve(content, locale) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

export function useDashboard(role, { auto = true } = {}) {
  const { locale } = useI18n()
  const summary = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')

  function localized(content) {
    return resolve(content, locale.value)
  }

  const actions = computed(() => summary.value?.actions ?? [])

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await dashboardService.getSummary(role)
      summary.value = data ?? null
      status.value = summary.value ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.response?.data?.message || ''
      status.value = 'error'
    }
  }

  if (auto) onMounted(load)

  return { summary, actions, status, errorMessage, load, localized }
}
