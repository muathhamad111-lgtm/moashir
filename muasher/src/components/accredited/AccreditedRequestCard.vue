<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ProgressMeter from '@/components/ui/ProgressMeter.vue'
import { formatRelativeTime } from '@/utils/datetime'
import { riyadhYear } from '@/utils/riyadhYear'

const JOURNEY_STATION = 3

const props = defineProps({
  assessment: { type: Object, required: true },
  progress: { type: Object, required: true },
  dirty: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const savedAt = computed(() =>
  props.assessment.updatedAt ? formatRelativeTime(props.assessment.updatedAt, locale.value) : '',
)
</script>

<template>
  <section class="request-card" aria-labelledby="request-card-title">
    <div class="request-card__head">
      <div class="request-card__identity">
        <h2 id="request-card-title" class="request-card__title">
          {{ t('portal.assessments.task.title', { station: JOURNEY_STATION }) }}
          <bdi dir="ltr">{{ riyadhYear(assessment.createdAt) }}</bdi>
        </h2>
        <div class="request-card__badges">
          <StatusBadge :status="assessment.status" />
          <Badge v-if="dirty" variant="warning">{{ t('portal.assessments.task.unsaved') }}</Badge>
          <Badge v-else-if="savedAt" variant="muted">{{
            t('portal.assessments.task.savedAt', { time: savedAt })
          }}</Badge>
          <Badge variant="info"
            ><bdi dir="ltr">{{ assessment.number }}</bdi></Badge
          >
        </div>
      </div>
      <slot name="actions" />
    </div>
    <ProgressMeter
      :label="t('portal.assessments.current.progress')"
      :current="progress.complete"
      :total="progress.total"
    />
  </section>
</template>
