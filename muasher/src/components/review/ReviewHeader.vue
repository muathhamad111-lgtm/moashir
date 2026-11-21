<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { REVIEW_MODES } from '@/constants/accredited'
import { localizedText } from '@/utils/localizedText'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  review: { type: Object, required: true },
})

const { t, n, locale } = useI18n()
const entityName = computed(() => localizedText(props.review.entity?.name, locale.value))
const progressLabel = computed(() =>
  t('review.progressLabel', {
    scored: props.review.progress.scored,
    required: props.review.progress.required,
  }),
)
const progressRange = computed(() =>
  t('portal.home.range', {
    done: formatNumber(n, props.review.progress.scored),
    total: formatNumber(n, props.review.progress.required),
  }),
)
</script>

<!-- One context line above the tabs; the full request facts live in «بيانات الطلب». -->
<template>
  <dl class="review-header review-bar">
    <div class="review-bar__item review-bar__item--entity">
      <dt>{{ t('requests.columns.entity') }}</dt>
      <dd :title="entityName">{{ entityName }}</dd>
    </div>
    <div v-if="review.assessment.path" class="review-bar__item">
      <dt>{{ t('requests.columns.path') }}</dt>
      <dd>
        <PathBadge :path="review.assessment.path" :label="review.assessment.pathLabel" compact />
      </dd>
    </div>
    <div class="review-bar__item">
      <dt>{{ t('requests.columns.status') }}</dt>
      <dd>
        <StatusBadge :status="review.assessment.status" />
        <Badge v-if="review.mode === REVIEW_MODES.READ_ONLY" variant="muted"
          ><AppIcon name="eye" /> {{ t('review.readOnly') }}</Badge
        >
        <Badge v-if="review.assessment.isFrozen" variant="danger"
          ><AppIcon name="snowflake" /> {{ t('accredited.form.frozen') }}</Badge
        >
      </dd>
    </div>
    <div class="review-bar__item review-bar__item--due">
      <dt>{{ t('requests.columns.dueAt') }}</dt>
      <dd>
        <AppIcon name="calendar" class="review-bar__icon" />
        <DateStamp :value="review.clock.dueAt" :with-time="false" />
        <OverdueBadge
          v-if="review.clock.dueAt && !review.clock.completedAt"
          :overdue="review.clock.isOverdue"
        />
      </dd>
    </div>
    <div class="review-bar__item review-bar__item--progress">
      <dt>{{ t('review.progress') }}</dt>
      <dd>
        <progress
          class="review-bar__progress"
          :value="review.progress.scored"
          :max="review.progress.required || 1"
          :aria-label="progressLabel"
        ></progress>
        <bdi class="review-header__progress-text" dir="ltr" :title="progressLabel">{{
          progressRange
        }}</bdi>
      </dd>
    </div>
  </dl>
</template>
