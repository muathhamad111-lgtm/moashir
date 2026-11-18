<script setup>
import { percentText } from '@/utils/percentText'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import AwardStateBadge from './AwardStateBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AssignedRequestProgress from '@/components/requests/AssignedRequestProgress.vue'
import { ASSIGNMENT_STATUSES, SEAT_STATUSES } from '@/constants/committee'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { committeeEntityLabel } from '@/utils/award/committeeEntity'
import { localizedText } from '@/utils/localizedText'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  sort: { type: String, default: '' },
})

const emit = defineEmits(['retry', 'sort'])
const { t, locale, n } = useI18n()

const columns = computed(() => [
  { key: 'entity', label: t('award.work.columns.entity') },
  { key: 'status', label: t('award.work.columns.status'), sortKey: 'status' },
  { key: 'dueAt', label: t('award.work.columns.dueAt'), sortKey: 'due_at' },
  { key: 'progress', label: t('requests.assigned.columns.progress') },
  { key: 'percent', label: t('award.work.columns.percent') },
  { key: 'open', label: t('requests.assigned.columns.actions') },
])

function isClosed(row) {
  return (
    row.status === ASSIGNMENT_STATUSES.COMPLETED || row.membershipStatus !== SEAT_STATUSES.ACTIVE
  )
}

// Same wording as the assessor's assigned requests: start, continue, or view the assessment.
function openLabel(row) {
  if (isClosed(row)) return t('requests.assigned.actions.viewReview')
  return row.status === ASSIGNMENT_STATUSES.NOT_STARTED
    ? t('requests.assigned.actions.start')
    : t('requests.assigned.actions.continue')
}
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('award.work.emptyTitle')"
    :empty-message="t('award.work.emptyMessage')"
    :sort="sort"
    @sort="emit('sort', $event)"
    @retry="emit('retry')"
  >
    <template #cell-entity="{ item }">
      <bdi class="committee-work__entity" :class="{ 'u-ltr': item.entity?.code }">{{
        committeeEntityLabel(item.entity, locale)
      }}</bdi>
      <span v-if="item.entity?.region" class="request-queue__muted">{{
        localizedText(item.entity.region.name, locale)
      }}</span>
      <span class="request-queue__muted">{{ localizedText(item.cycle?.name, locale) }}</span>
    </template>
    <template #cell-status="{ item }">
      <span class="committee-work__badges">
        <AwardStateBadge kind="row" :status="item.status" />
        <Badge v-if="item.isSubstitute" variant="info">{{ t('award.work.substitute') }}</Badge>
        <Badge
          v-if="item.returnedAt && item.status !== ASSIGNMENT_STATUSES.COMPLETED"
          variant="warning"
          >{{ t('award.work.returned') }}</Badge
        >
        <AwardStateBadge
          v-if="item.membershipStatus !== SEAT_STATUSES.ACTIVE"
          kind="seat"
          :status="item.membershipStatus"
        />
      </span>
    </template>
    <template #cell-dueAt="{ item }">
      <span class="committee-work__badges">
        <DateStamp :value="item.dueAt" :with-time="false" />
        <OverdueBadge v-if="item.isOverdue" :overdue="true" />
      </span>
    </template>
    <template #cell-percent="{ item }"
      ><bdi v-if="item.percent" class="u-ltr">{{ percentText(n, t, item.percent) }}</bdi
      ><span v-else class="committee-member__note">{{
        t('award.work.notSubmitted')
      }}</span></template
    >
    <template #cell-progress="{ item }">
      <AssignedRequestProgress :progress="item.reviewProgress" />
    </template>
    <template #cell-open="{ item }">
      <BaseButton
        :variant="isClosed(item) ? 'secondary' : 'primary'"
        size="sm"
        :to="{ name: ROUTE_NAMES.AWARD_COMMITTEE_SCORE, params: { assignment: item.id } }"
        >{{ openLabel(item) }}</BaseButton
      >
    </template>
  </DataTable>
</template>
