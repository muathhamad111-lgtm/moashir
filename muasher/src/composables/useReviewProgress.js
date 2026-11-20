import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { scoredStationsOf } from '@/utils/assessments/scoredStations'
import { scoredCount } from '@/utils/assessments/reviewRows'
import { localizedText } from '@/utils/localizedText'

// Where the assessor stands, from the values on screen (saved or not): each criterion's state for
// the strip, how many items are still unscored, and the name of the next criterion for «التالي».
export function useReviewProgress({ mains, units, scoreSheet, steps }) {
  const { locale } = useI18n()

  const stations = computed(() =>
    scoredStationsOf(mains.value, units.value, scoreSheet.sheet.values),
  )
  const missing = computed(
    () => units.value.length - scoredCount(scoreSheet.sheet.values, units.value),
  )
  const nextName = computed(() => {
    const next = mains.value[steps.position.value + 1]
    return next ? localizedText(next.name, locale.value) : ''
  })

  return { stations, missing, nextName }
}
