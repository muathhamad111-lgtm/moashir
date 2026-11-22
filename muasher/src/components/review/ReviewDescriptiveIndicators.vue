<script setup>
import { useI18n } from 'vue-i18n'
import ReviewEvidenceRows from './ReviewEvidenceRows.vue'
import { localizedText } from '@/utils/localizedText'

// The indicators of a directly evaluated sub-criterion with the evidence uploaded for each. They
// have no score of their own: the assessor judges the whole sub-criterion once (operator, 2026-11-22).
defineProps({
  points: { type: Array, required: true },
  evidenceByPoint: { type: Map, required: true },
  downloading: { type: Object, required: true },
  newIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()
</script>

<template>
  <section class="review-card__section">
    <h4 class="review-card__label">{{ t('review.directIndicators') }}</h4>
    <p class="review-card__hint">{{ t('review.directIndicatorsHint') }}</p>
    <ul class="review-card__items">
      <li v-for="point in points" :key="point.id" class="review-item review-item--descriptive">
        <div class="review-item__text">
          <p class="review-item__title">{{ localizedText(point.description, locale) }}</p>
          <p class="review-item__meta">
            <bdi class="criteria-code">{{ point.code }}</bdi>
          </p>
        </div>
        <ReviewEvidenceRows
          v-if="evidenceByPoint.get(point.id)?.length"
          :rows="evidenceByPoint.get(point.id)"
          :downloading="downloading"
          :new-ids="newIds"
          @download="emit('download', $event)"
        />
      </li>
    </ul>
  </section>
</template>
