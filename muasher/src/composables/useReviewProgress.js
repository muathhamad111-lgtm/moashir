import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { scoredStationsOf } from '@/utils/assessments/scoredStations'
import { scoredCount, subUnits } from '@/utils/assessments/reviewRows'
import { isAwaitingRescore } from '@/utils/assessments/reviewRounds'
import { STATION_STATES } from '@/utils/accredited/submissionProgress'
import { localizedText } from '@/utils/localizedText'

// Where the assessor stands, from the values on screen (saved or not): each criterion's state for
// the strip, how many items are still unscored, and the name of the next criterion for «التالي».
// After a completion, the items waiting for a new result count per criterion and keep it amber
// (design §B.3.3).
export function useReviewProgress({ mains, units, scoreSheet, steps, rounds }) {
  const { locale } = useI18n()

  function awaitingIn(main) {
    return main.subs.reduce(
      (count, sub) =>
        count +
        subUnits(sub).filter((unit) => isAwaitingRescore(rounds?.value, unit, sub.code)).length,
      0,
    )
  }

  const stations = computed(() =>
    scoredStationsOf(mains.value, units.value, scoreSheet.sheet.values).map((station, index) => {
      const awaiting = awaitingIn(mains.value[index])
      const state =
        awaiting && station.state === STATION_STATES.COMPLETE
          ? STATION_STATES.PARTIAL
          : station.state
      return { ...station, awaiting, state }
    }),
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
