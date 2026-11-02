<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { ASSIGNMENT_STATUSES } from '@/constants/accredited'

defineProps({
  history: { type: Array, required: true },
})

const { t } = useI18n()

const VARIANTS = {
  [ASSIGNMENT_STATUSES.ACTIVE]: 'info',
  [ASSIGNMENT_STATUSES.REASSIGNED]: 'warning',
  [ASSIGNMENT_STATUSES.COMPLETED]: 'success',
  [ASSIGNMENT_STATUSES.ENDED]: 'muted',
}
</script>

<template>
  <ol v-if="history.length" class="assignment-history">
    <li v-for="entry in history" :key="entry.id" class="assignment-history__item">
      <div class="assignment-history__head">
        <span class="assignment-history__name">{{ entry.assessor?.name ?? t('assessment.timeline.actors.staff') }}</span>
        <Badge :variant="VARIANTS[entry.status] ?? 'muted'">{{ t(`requests.assignment.statuses.${entry.status}`) }}</Badge>
      </div>
      <dl class="assignment-history__facts">
        <div><dt>{{ t('requests.assignment.assignedAt') }}</dt><dd><DateStamp :value="entry.assignedAt" /></dd></div>
        <div v-if="entry.startedAt"><dt>{{ t('requests.assignment.startedAt') }}</dt><dd><DateStamp :value="entry.startedAt" /></dd></div>
        <div v-if="entry.endedAt"><dt>{{ t('requests.assignment.endedAt') }}</dt><dd><DateStamp :value="entry.endedAt" /></dd></div>
        <div v-if="'assignedBy' in entry">
          <dt>{{ t('requests.assignment.assignedBy') }}</dt>
          <dd>{{ entry.assignedBy?.name ?? t('assessment.timeline.actors.staff') }}</dd>
        </div>
        <div v-if="entry.reason"><dt>{{ t('requests.assignment.reason') }}</dt><dd>{{ entry.reason }}</dd></div>
        <div v-if="entry.endReason"><dt>{{ t('requests.assignment.endReason') }}</dt><dd>{{ t(`requests.assignment.endReasons.${entry.endReason}`) }}</dd></div>
      </dl>
    </li>
  </ol>
  <p v-else class="assignment-history__empty">{{ t('requests.detail.noHistory') }}</p>
</template>
