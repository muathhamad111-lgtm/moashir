<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { isAchievement, maximumOf } from '@/utils/assessments/scoringTypes'
import { compactDecimal } from '@/utils/percentText'
import { formatRiyadhDate } from '@/utils/datetime'
import { SCORING_TYPES } from '@/constants/criteria'

// An item's completion rounds (design §B.3.3): the result before the completion stays visible and
// read-only; the current round's result is scored in the control above. While it waits, the
// assessor may keep the earlier result as this round's.
defineProps({
  rows: { type: Array, required: true },
  scoringType: { type: String, required: true },
  label: { type: String, required: true },
  canConfirm: { type: Boolean, default: false },
  confirming: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm'])
const { t, locale } = useI18n()

const ACHIEVED_PERCENT = 100

function resultOf(entry, scoringType) {
  if (isAchievement(scoringType))
    return t(
      Number(entry.percent) >= ACHIEVED_PERCENT
        ? 'review.rounds.achieved'
        : 'review.rounds.notAchieved',
    )
  if (scoringType === SCORING_TYPES.RELATIVE)
    return t('review.rounds.relative', { value: compactDecimal(entry.rawValue) })
  return t('review.rounds.score', {
    value: compactDecimal(entry.rawValue),
    max: compactDecimal(maximumOf(scoringType)),
  })
}

function roundLabel(round) {
  return round === 1
    ? t('review.rounds.before')
    : t('review.rounds.after', { round: compactDecimal(round) })
}
</script>

<template>
  <div class="review-rounds">
    <table class="review-rounds__table">
      <caption class="review-rounds__caption">
        {{
          t('review.rounds.caption', { name: label })
        }}
      </caption>
      <thead>
        <tr>
          <th scope="col">{{ t('review.rounds.round') }}</th>
          <th scope="col">{{ t('review.rounds.date') }}</th>
          <th scope="col">{{ t('review.rounds.result') }}</th>
          <th scope="col">{{ t('review.rounds.note') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="row.round"
          class="review-rounds__row"
          :class="{ 'is-pending': row.pending }"
        >
          <th scope="row">{{ roundLabel(row.round) }}</th>
          <td>
            <bdi v-if="row.entry">{{ formatRiyadhDate(row.entry.scoredAt, locale) }}</bdi>
            <span v-else aria-hidden="true">—</span>
          </td>
          <td>
            <bdi v-if="row.entry">{{ resultOf(row.entry, scoringType) }}</bdi>
            <div v-else-if="row.pending" class="review-rounds__pending">
              <Badge variant="warning">{{ t('review.rounds.awaiting') }}</Badge>
              <BaseButton
                v-if="canConfirm"
                variant="ghost"
                size="sm"
                icon="check"
                :loading="confirming"
                @click="emit('confirm')"
              >
                {{ t('review.rounds.keep') }}
              </BaseButton>
            </div>
          </td>
          <td>{{ row.note || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
