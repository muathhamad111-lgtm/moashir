import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { scoreFieldName } from '@/utils/assessments/scoreSheet'
import { isAchievement } from '@/utils/assessments/scoringTypes'
import { compactDecimal } from '@/utils/percentText'

const ACHIEVED_PERCENT = 100

// What an item shows in the override mode (design §B.3.5): its published value beside the field
// once it is changed, and a highlight on the changed row.
export function useOverrideRows(workspace) {
  const { t } = useI18n()
  const override = workspace.override
  const active = computed(() => Boolean(override?.active.value))
  const changedKeys = computed(
    () => new Set((override?.changedUnits.value ?? []).map((unit) => scoreFieldName(unit))),
  )

  function publishedText(unit, scoringType) {
    const saved = override.saved(unit)
    if (!saved) return t('review.override.noPublished')
    if (isAchievement(scoringType))
      return t(
        Number(saved.percent) >= ACHIEVED_PERCENT
          ? 'review.rounds.achieved'
          : 'review.rounds.notAchieved',
      )
    return compactDecimal(saved.rawValue)
  }

  const isChanged = (unit) => active.value && changedKeys.value.has(scoreFieldName(unit))

  function changedLabel(unit, scoringType) {
    if (!active.value) return ''
    return t('review.override.publishedValue', { value: publishedText(unit, scoringType) })
  }

  return { active, isChanged, changedLabel }
}
