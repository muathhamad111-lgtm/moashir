<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import ReviewScoreControl from './ReviewScoreControl.vue'
import ReviewEvidenceRows from './ReviewEvidenceRows.vue'
import ReviewRoundRows from './ReviewRoundRows.vue'
import { localizedText } from '@/utils/localizedText'
import { formatShare, shareOf } from '@/utils/weight'

// One indicator: its text and meta on the start side, its score facing it on the end side, and
// the evidence the entity uploaded for it underneath (design §B.3.1). A scored row turns green; a
// row waiting for a new result after a completion turns amber, with its rounds under the evidence
// (§B.3.3).
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
  // From the review's completion rounds: { rows, awaiting, newIds, canConfirm, confirming }.
  round: { type: Object, default: null },
  // The override mode: the published value beside a changed field, and the changed highlight.
  changedLabel: { type: String, default: '' },
  changed: { type: Boolean, default: false },
})

const emit = defineEmits(['download', 'confirm'])
const { t, locale } = useI18n()

const text = computed(() => localizedText(props.point.description, locale.value))
const awaiting = computed(() => Boolean(props.round?.awaiting))
const stateKey = computed(() => {
  if (awaiting.value) return 'awaiting'
  return props.scored ? 'scored' : 'pending'
})
const share = computed(() =>
  formatShare(shareOf(props.point.weight, props.sub.weight), locale.value),
)
</script>

<template>
  <li
    class="review-item"
    :class="{ 'is-scored': scored && !awaiting, 'is-awaiting': awaiting, 'is-changed': changed }"
  >
    <div class="review-item__head">
      <span
        class="review-state"
        :class="{ 'is-done': scored && !awaiting, 'is-awaiting': awaiting }"
        aria-hidden="true"
      >
        <AppIcon v-if="scored && !awaiting" name="check" />
      </span>
      <div class="review-item__text">
        <p class="review-item__title">{{ text }}</p>
        <p class="review-item__meta">
          <bdi class="criteria-code">{{ point.code }}</bdi>
          <span>{{ t(`criteria.scoringTypes.${scoringType}.label`) }}</span>
          <span>{{ t('review.shareOfSub', { share }) }}</span>
          <span class="review-item__sr">{{ t(`review.state.${stateKey}`) }}</span>
        </p>
      </div>
      <ReviewScoreControl
        class="review-item__control"
        :name="name"
        :label="text"
        :scoring-type="scoringType"
        :saved="saved"
        :disabled="disabled"
        :changed-label="changedLabel"
      />
    </div>
    <ReviewEvidenceRows
      v-if="evidence.length"
      :rows="evidence"
      :downloading="downloading"
      :new-ids="round?.newIds ?? []"
      @download="emit('download', $event)"
    />
    <ReviewRoundRows
      v-if="round?.rows.length"
      :rows="round.rows"
      :scoring-type="scoringType"
      :label="text"
      :can-confirm="round.canConfirm"
      :confirming="round.confirming"
      @confirm="emit('confirm')"
    />
  </li>
</template>
