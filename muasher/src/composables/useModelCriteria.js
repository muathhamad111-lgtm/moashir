import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { contentService } from '@/services/contentService'
import { CRITERION_GROUPS } from '@/constants/criteria'

function resolve(content, locale) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

export function useModelCriteria({ auto = true } = {}) {
  const { locale } = useI18n()
  const criteria = ref([])
  const status = ref('loading')

  function localized(value) {
    return resolve(value, locale.value)
  }

  const byOrder = (left, right) => left.order - right.order
  const inGroup = (group) =>
    computed(() => criteria.value.filter((item) => item.group === group).sort(byOrder))
  const enablers = inGroup(CRITERION_GROUPS.ENABLERS)
  const creativity = inGroup(CRITERION_GROUPS.CREATIVITY_LEARNING)
  const results = inGroup(CRITERION_GROUPS.RESULTS)

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await contentService.getModelCriteria()
      criteria.value = data ?? []
      status.value = criteria.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  if (auto) onMounted(load)

  return { criteria, enablers, creativity, results, status, load, localized }
}
