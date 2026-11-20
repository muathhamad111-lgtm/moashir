<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import ReviewScoreControl from './ReviewScoreControl.vue'
import ReviewEvidenceRows from './ReviewEvidenceRows.vue'
import { localizedText } from '@/utils/localizedText'
import { formatShare, shareOf } from '@/utils/weight'

// One indicator: its text and meta on the start side, its score facing it on the end side, and
// the evidence the entity uploaded for it underneath (design §B.3.1). A scored row turns green.
const props = defineProps({
  point: { type: Object, required: true },
  sub: { type: Object, required: true },
  name: { type: String, required: true },
  scoringType: { type: String, required: true },
  saved: { type: Object, default: null },
  scored: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  evidence: { type: Array, default: () => [] },
  downloading: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

const text = computed(() => localizedText(props.point.description, locale.value))
const share = computed(() =>
  formatShare(shareOf(props.point.weight, props.sub.weight), locale.value),
)
</script>

<template>
  <li class="review-item" :class="{ 'is-scored': scored }">
    <div class="review-item__head">
      <span class="review-state" :class="{ 'is-done': scored }" aria-hidden="true">
        <AppIcon v-if="scored" name="check" />
      </span>
      <div class="review-item__text">
        <p class="review-item__title">{{ text }}</p>
        <p class="review-item__meta">
          <bdi class="criteria-code">{{ point.code }}</bdi>
          <span>{{ t(`criteria.scoringTypes.${scoringType}.label`) }}</span>
          <span>{{ t('review.shareOfSub', { share }) }}</span>
          <span class="review-item__sr">{{
            t(scored ? 'review.state.scored' : 'review.state.pending')
          }}</span>
        </p>
      </div>
      <ReviewScoreControl
        class="review-item__control"
        :name="name"
        :label="text"
        :scoring-type="scoringType"
        :saved="saved"
        :disabled="disabled"
      />
    </div>
    <ReviewEvidenceRows
      v-if="evidence.length"
      :rows="evidence"
      :downloading="downloading"
      @download="emit('download', $event)"
    />
  </li>
</template>
