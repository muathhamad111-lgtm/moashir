<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import AwardStateBadge from './AwardStateBadge.vue'
import { REVIEW_MODES } from '@/constants/accredited'
import { SEAT_STATUSES } from '@/constants/committee'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  evaluation: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <dl class="review-header">
    <div class="review-header__item">
      <dt>{{ t('award.evaluation.status') }}</dt>
      <dd class="review-header__badges">
        <AwardStateBadge kind="row" :status="evaluation.status" />
        <Badge v-if="evaluation.mode === REVIEW_MODES.READ_ONLY" variant="muted"
          ><AppIcon name="eye" /> {{ t('review.readOnly') }}</Badge
        >
        <Badge v-if="evaluation.assignment.isSubstitute" variant="info"
          ><AppIcon name="users-two" /> {{ t('award.work.substitute') }}</Badge
        >
        <AwardStateBadge
          v-if="evaluation.assignment.membershipStatus !== SEAT_STATUSES.ACTIVE"
          kind="seat"
          :status="evaluation.assignment.membershipStatus"
        />
      </dd>
    </div>
    <div class="review-header__item">
      <dt>{{ t('review.progress') }}</dt>
      <dd>
        <progress
          class="review-header__progress"
          :value="evaluation.progress.scored"
          :max="evaluation.progress.required || 1"
          :aria-label="
            t('review.progressLabel', {
              scored: evaluation.progress.scored,
              required: evaluation.progress.required,
            })
          "
        ></progress>
        <span class="review-header__progress-text">{{
          t('review.progressLabel', {
            scored: evaluation.progress.scored,
            required: evaluation.progress.required,
          })
        }}</span>
      </dd>
    </div>
    <div class="review-header__item">
      <dt>{{ t('award.work.dueAt') }}</dt>
      <dd class="review-header__badges">
        <DateStamp :value="evaluation.assignment.dueAt" />
        <OverdueBadge v-if="evaluation.assignment.isOverdue" :overdue="true" />
      </dd>
    </div>
    <div v-if="evaluation.percent" class="review-header__item">
      <dt>{{ t('award.evaluation.myTotal') }}</dt>
      <dd>
        <bdi class="u-ltr">{{ evaluation.percent }}%</bdi>
      </dd>
    </div>
  </dl>
</template>
