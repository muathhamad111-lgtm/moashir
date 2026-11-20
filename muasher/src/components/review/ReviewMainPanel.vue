<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import ReviewSubPanel from './ReviewSubPanel.vue'
import MainEvidenceReview from './MainEvidenceReview.vue'
import { localizedText } from '@/utils/localizedText'
import { formatShare, shareOf } from '@/utils/weight'
import { scoredCount, subUnits } from '@/utils/assessments/reviewRows'

// The criterion being reviewed (design §B.3.1): where it sits in the model, how much of it is
// scored, its own attachments, then one card per sub-criterion.
const props = defineProps({
  main: { type: Object, required: true },
  step: { type: Number, required: true },
  total: { type: Number, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

const review = computed(() => props.workspace.reviewState.review.value)
const units = computed(() => props.main.subs.flatMap(subUnits))
const scored = computed(() => scoredCount(props.workspace.scoreSheet.sheet.values, units.value))
const share = computed(() =>
  formatShare(shareOf(props.main.weight, review.value.criteria.totalWeight), locale.value),
)
const variant = computed(() => {
  if (scored.value === units.value.length) return 'success'
  return scored.value ? 'warning' : 'muted'
})
</script>

<template>
  <section class="review-main review-criterion" :aria-labelledby="`review-main-${main.id}`">
    <header class="review-criterion__head">
      <div>
        <p class="review-criterion__position">
          {{ t('review.criterionPosition', { step, total, share }) }}
        </p>
        <h2 :id="`review-main-${main.id}`" class="review-criterion__title">
          {{ localizedText(main.name, locale) }}
        </h2>
      </div>
      <Badge :variant="variant" class="review-criterion__count">
        {{ t('review.itemsScored', { scored, total: units.length }) }}
      </Badge>
    </header>
    <MainEvidenceReview
      v-if="main.attachments?.length"
      :main="main"
      :evidence="review.evidence"
      :late-keys="review.lateAttachmentKeys"
      :downloading="workspace.downloads.busy"
      @download="emit('download', $event)"
    />
    <ul class="review-criterion__subs">
      <ReviewSubPanel
        v-for="sub in main.subs"
        :key="sub.id"
        :sub="sub"
        :main="main"
        :workspace="workspace"
        @download="emit('download', $event)"
      />
    </ul>
  </section>
</template>
