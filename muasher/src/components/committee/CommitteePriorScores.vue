<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { savedScoreOf } from '@/utils/assessments/scoreSheet'
import { achievementKeyOf, isAchievement } from '@/utils/assessments/scoringTypes'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  prior: { type: Object, required: true },
  units: { type: Array, required: true },
  subId: { type: Number, required: true },
  // The scoring type of a unit in this request (the score sheet's typeOf).
  typeOf: { type: Function, required: true },
})

const { t, locale } = useI18n()
const rows = computed(() =>
  props.units.map((unit) => ({ unit, score: savedScoreOf(props.prior.scores, unit) })),
)
function scoreText(unit, score) {
  if (!score) return t('award.evaluation.prior.none')
  if (isAchievement(props.typeOf(unit)))
    return t(`assessment.self.achievement.${achievementKeyOf(score.rawValue)}`)
  return t('award.evaluation.prior.score', { raw: score.rawValue, percent: score.percent })
}
const note = computed(() => props.prior.notes.subs[String(props.subId)] ?? '')
</script>

<template>
  <aside class="committee-prior" :aria-label="t('award.evaluation.prior.label')">
    <p class="committee-prior__title">
      <AppIcon name="eye" /> {{ t('award.evaluation.prior.title') }}
    </p>
    <ul class="committee-prior__scores">
      <li
        v-for="row in rows"
        :key="`${row.unit.level}-${row.unit.id}`"
        class="committee-prior__score"
      >
        <span>{{ localizedText(row.unit.label, locale) }}</span>
        <span>{{ scoreText(row.unit, row.score) }}</span>
      </li>
    </ul>
    <p v-if="note" class="committee-prior__note">{{ note }}</p>
  </aside>
</template>
