<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { savedScoreOf } from '@/utils/assessments/scoreSheet'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  prior: { type: Object, required: true },
  units: { type: Array, required: true },
  subId: { type: Number, required: true },
})

const { t } = useI18n()
const rows = computed(() =>
  props.units.map((unit) => ({ unit, score: savedScoreOf(props.prior.scores, unit) })),
)
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
        <bdi class="u-ltr">{{ row.unit.code }}</bdi>
        <span>{{
          row.score
            ? t('award.evaluation.prior.score', {
                raw: row.score.rawValue,
                percent: row.score.percent,
              })
            : t('award.evaluation.prior.none')
        }}</span>
      </li>
    </ul>
    <p v-if="note" class="committee-prior__note">{{ note }}</p>
  </aside>
</template>
