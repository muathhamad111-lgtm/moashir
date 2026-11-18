<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AwardStateBadge from './AwardStateBadge.vue'
import { REVIEW_MODES } from '@/constants/accredited'
import { SEAT_STATUSES } from '@/constants/committee'
import { committeeEntityLabel } from '@/utils/award/committeeEntity'
import { localizedText } from '@/utils/localizedText'
import { formatNumber } from '@/utils/formatNumber'
import { percentText } from '@/utils/percentText'

const props = defineProps({
  evaluation: { type: Object, required: true },
})

const { t, n, locale } = useI18n()
const assignment = computed(() => props.evaluation.assignment)
const entityName = computed(() => committeeEntityLabel(assignment.value.entity, locale.value))
const progressLabel = computed(() =>
  t('review.progressLabel', {
    scored: props.evaluation.progress.scored,
    required: props.evaluation.progress.required,
  }),
)
const progressRange = computed(() =>
  t('portal.home.range', {
    done: formatNumber(n, props.evaluation.progress.scored),
    total: formatNumber(n, props.evaluation.progress.required),
  }),
)
</script>

<!-- The committee assessor's one-line context bar, the same pattern as the review screen. -->
<template>
  <dl class="review-header review-bar">
    <div class="review-bar__item review-bar__item--entity">
      <dt>{{ t('award.work.columns.entity') }}</dt>
      <dd :title="entityName">
        <bdi :class="{ 'u-ltr': assignment.entity?.code }">{{ entityName }}</bdi>
      </dd>
    </div>
    <div v-if="assignment.cycle" class="review-bar__item">
      <dt>{{ t('award.work.filters.cycle') }}</dt>
      <dd class="review-bar__muted">{{ localizedText(assignment.cycle.name, locale) }}</dd>
    </div>
    <div class="review-bar__item">
      <dt>{{ t('award.evaluation.status') }}</dt>
      <dd>
        <AwardStateBadge kind="row" :status="evaluation.status" />
        <Badge v-if="evaluation.mode === REVIEW_MODES.READ_ONLY" variant="muted"
          ><AppIcon name="eye" /> {{ t('review.readOnly') }}</Badge
        >
        <Badge v-if="assignment.isSubstitute" variant="info"
          ><AppIcon name="users-two" /> {{ t('award.work.substitute') }}</Badge
        >
        <AwardStateBadge
          v-if="assignment.membershipStatus !== SEAT_STATUSES.ACTIVE"
          kind="seat"
          :status="assignment.membershipStatus"
        />
      </dd>
    </div>
    <div class="review-bar__item review-bar__item--due">
      <dt>{{ t('award.work.dueAt') }}</dt>
      <dd>
        <AppIcon name="calendar" class="review-bar__icon" />
        <DateStamp :value="assignment.dueAt" :with-time="false" />
        <OverdueBadge v-if="assignment.isOverdue" :overdue="true" />
      </dd>
    </div>
    <div v-if="evaluation.percent" class="review-bar__item">
      <dt>{{ t('award.evaluation.myTotal') }}</dt>
      <dd>
        <bdi class="u-ltr">{{ percentText(n, t, evaluation.percent) }}</bdi>
      </dd>
    </div>
    <div class="review-bar__item review-bar__item--progress">
      <dt>{{ t('review.progress') }}</dt>
      <dd>
        <progress
          class="review-bar__progress"
          :value="evaluation.progress.scored"
          :max="evaluation.progress.required || 1"
          :aria-label="progressLabel"
        ></progress>
        <bdi class="review-header__progress-text" dir="ltr" :title="progressLabel">{{
          progressRange
        }}</bdi>
      </dd>
    </div>
  </dl>
</template>
