<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import AwardStateBadge from './AwardStateBadge.vue'
import { ASSIGNMENT_STATUSES, SEAT_STATUSES } from '@/constants/committee'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { committeeEntityLabel } from '@/utils/award/committeeEntity'
import { localizedText } from '@/utils/localizedText'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const columns = computed(() => [
  { key: 'entity', label: t('award.work.columns.entity') },
  { key: 'cycle', label: t('award.work.columns.cycle') },
  { key: 'status', label: t('award.work.columns.status') },
  { key: 'dueAt', label: t('award.work.columns.dueAt') },
  { key: 'percent', label: t('award.work.columns.percent') },
  { key: 'open', label: t('award.work.columns.open') },
])

function openLabel(row) {
  if (row.status === ASSIGNMENT_STATUSES.COMPLETED || row.membershipStatus !== SEAT_STATUSES.ACTIVE)
    return t('award.work.view')
  return row.status === ASSIGNMENT_STATUSES.NOT_STARTED
    ? t('award.work.start')
    : t('award.work.continue')
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
    @retry="emit('retry')"
  >
    <template #cell-entity="{ item }">
      <bdi class="committee-work__entity" :class="{ 'u-ltr': item.entity?.code }">{{
        committeeEntityLabel(item.entity, locale)
      }}</bdi>
      <span v-if="item.entity?.region" class="committee-member__note">{{
        localizedText(item.entity.region.name, locale)
      }}</span>
    </template>
    <template #cell-cycle="{ item }">{{ localizedText(item.cycle?.name, locale) }}</template>
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
      ><bdi v-if="item.percent" class="u-ltr">{{ item.percent }}%</bdi
      ><span v-else class="committee-member__note">{{
        t('award.work.notSubmitted')
      }}</span></template
    >
    <template #cell-open="{ item }">
      <RouterLink
        class="cycle-table__link"
        :to="{ name: ROUTE_NAMES.AWARD_COMMITTEE_SCORE, params: { assignment: item.id } }"
        >{{ openLabel(item) }}</RouterLink
      >
    </template>
  </DataTable>
</template>
