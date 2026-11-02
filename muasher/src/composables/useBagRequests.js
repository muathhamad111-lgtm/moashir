import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { bagRequestsService } from '@/services/bagRequestsService'
import { statusLabelKey } from '@/utils/canonicalStatus'

export const BAG_REQUEST_STATUSES = [
  'awaiting-assignment',
  'assigned',
  'in-analysis',
  'plan-draft',
  'plan-ready',
  'active',
  'stage-review',
  'completed',
  'suspended',
  'cancelled',
]

function localizedFactory(locale) {
  return function localized(content) {
    if (content === null || content === undefined) return ''
    if (typeof content === 'string') return content
    return content[locale.value] || content.ar || content.en || ''
  }
}

export function useBagRequests() {
  const { t, locale } = useI18n()
  const localized = localizedFactory(locale)

  const statusFilterOptions = computed(() => [
    { value: '', label: t('bagRequests.filters.allStatuses') },
    ...BAG_REQUEST_STATUSES.map((value) => ({ value, label: t(statusLabelKey(value)) })),
  ])

  const evaluators = ref([])
  const evaluatorsLoading = ref(false)

  async function loadEvaluators() {
    evaluatorsLoading.value = true
    try {
      const { data } = await bagRequestsService.listEvaluators()
      evaluators.value = data ?? []
    } catch {
      evaluators.value = []
    } finally {
      evaluatorsLoading.value = false
    }
  }

  const evaluatorOptions = computed(() =>
    evaluators.value.map((e) => ({ value: e.id, label: e.name })),
  )

  return {
    localized,
    statusFilterOptions,
    evaluators,
    evaluatorsLoading,
    evaluatorOptions,
    loadEvaluators,
  }
}
