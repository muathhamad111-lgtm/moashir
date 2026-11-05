<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import { REVIEW_MODES } from '@/constants/accredited'
import { localizedText } from '@/utils/localizedText'

defineProps({
  review: { type: Object, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <dl class="review-header">
    <div class="review-header__item">
      <dt>{{ t('requests.columns.number') }}</dt>
      <dd>
        <bdi dir="ltr">{{ review.assessment.number }}</bdi>
      </dd>
    </div>
    <div class="review-header__item">
      <dt>{{ t('requests.columns.entity') }}</dt>
      <dd>{{ localizedText(review.entity?.name, locale) }}</dd>
    </div>
    <div class="review-header__item">
      <dt>{{ t('requests.columns.status') }}</dt>
      <dd class="review-header__badges">
        <StatusBadge :status="review.assessment.status" />
        <Badge v-if="review.mode === REVIEW_MODES.READ_ONLY" variant="muted"
          ><i class="fa-solid fa-eye" aria-hidden="true"></i> {{ t('review.readOnly') }}</Badge
        >
        <Badge v-if="review.assessment.isFrozen" variant="danger"
          ><i class="fa-solid fa-snowflake" aria-hidden="true"></i>
          {{ t('accredited.form.frozen') }}</Badge
        >
      </dd>
    </div>
    <div class="review-header__item">
      <dt>{{ t('review.progress') }}</dt>
      <dd>
        <progress
          class="review-header__progress"
          :value="review.progress.scored"
          :max="review.progress.required || 1"
          :aria-label="
            t('review.progressLabel', {
              scored: review.progress.scored,
              required: review.progress.required,
            })
          "
        ></progress>
        <span class="review-header__progress-text">{{
          t('review.progressLabel', {
            scored: review.progress.scored,
            required: review.progress.required,
          })
        }}</span>
      </dd>
    </div>
    <div class="review-header__item">
      <dt>{{ t('requests.columns.dueAt') }}</dt>
      <dd class="review-header__badges">
        <DateStamp :value="review.clock.dueAt" />
        <OverdueBadge
          v-if="review.clock.dueAt && !review.clock.completedAt"
          :overdue="review.clock.isOverdue"
        />
      </dd>
    </div>
  </dl>
</template>
