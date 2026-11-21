import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SCORING_TYPE_ORDER } from '@/constants/criteria'

export function useScoringTypeOptions() {
  const { t } = useI18n()

  const labelOf = (type) => t(`criteria.scoringTypes.${type}.label`)
  const shortOf = (type) => t(`criteria.scoringTypes.${type}.short`)
  const options = computed(() =>
    SCORING_TYPE_ORDER.map((type) => ({ value: type, label: labelOf(type) })),
  )

  return { options, labelOf, shortOf }
}
