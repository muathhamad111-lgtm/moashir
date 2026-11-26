<script setup>
import { percentText } from '@/utils/percentText'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import { COLUMN_KINDS } from '@/utils/tableColumns'

defineProps({
  cycleId: { type: Number, required: true },
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale, n } = useI18n()
const { NUMBER, STATUS, CODE } = COLUMN_KINDS

const columns = computed(() => [
  { key: 'number', label: t('award.secretary.columns.number') },
  { key: 'entity', label: t('award.secretary.columns.entity') },
  { key: 'region', label: t('award.secretary.columns.region') },
  { key: 'status', label: t('award.secretary.columns.status') },
  { key: 'committee', label: t('award.secretary.columns.committee') },
  { key: 'average', label: t('award.secretary.columns.average') },
  { key: 'initialPercent', label: t('award.secretary.columns.initialPercent'), kind: NUMBER },
  { key: 'submittedAt', label: t('award.secretary.columns.submittedAt') },
  { key: 'dueAt', label: t('award.secretary.columns.dueAt') },
  { key: 'averageComputedAt', label: t('award.secretary.columns.averageComputedAt') },
  { key: 'assessorsCounted', label: t('award.secretary.columns.assessorsCounted'), kind: NUMBER },
  { key: 'manualAverage', label: t('award.secretary.columns.manualAverage'), kind: STATUS },
  { key: 'entityReference', label: t('award.secretary.columns.entityReference'), kind: CODE },
  { key: 'open', label: t('award.secretary.columns.open'), action: true },
])
</script>

<template>
  <DataTable
    table-key="award-secretary-requests"
    stacked
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('award.secretary.emptyTitle')"
    :empty-message="t('award.secretary.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-number="{ item }"
      ><bdi dir="ltr">{{ item.number }}</bdi></template
    >
    <template #cell-entity="{ item }">{{ localizedText(item.entity?.name, locale) }}</template>
    <template #cell-region="{ item }">{{ localizedText(item.region?.name, locale) }}</template>
    <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
    <template #cell-committee="{ item }">
      <span class="committee-work__badges">
        <span>{{
          t('award.secretary.progress', {
            completed: item.committee.completed,
            total: item.committee.assignments,
          })
        }}</span>
        <Badge v-if="item.committee.pendingSubstitutions" variant="warning">{{
          t(
            'award.secretary.pendingSubstitutions',
            { count: item.committee.pendingSubstitutions },
            item.committee.pendingSubstitutions,
          )
        }}</Badge>
      </span>
    </template>
    <template #cell-average="{ item }">
      <bdi v-if="item.average" class="u-ltr secretary-table__average">{{
        percentText(n, t, item.average.percent)
      }}</bdi>
      <span v-else class="committee-member__note">{{ t('award.secretary.noAverage') }}</span>
    </template>
    <template #cell-initialPercent="{ item }"
      ><bdi v-if="item.initialPercent" class="u-ltr">{{
        percentText(n, t, item.initialPercent)
      }}</bdi></template
    >
    <template #cell-submittedAt="{ item }"
      ><DateStamp v-if="item.submittedAt" :value="item.submittedAt" :with-time="false"
    /></template>
    <template #cell-dueAt="{ item }">
      <span v-if="item.dueAt" class="committee-work__badges">
        <DateStamp :value="item.dueAt" :with-time="false" />
        <OverdueBadge v-if="item.isOverdue" :overdue="true" />
      </span>
    </template>
    <template #cell-averageComputedAt="{ item }"
      ><DateStamp v-if="item.average" :value="item.average.computedAt" :with-time="false"
    /></template>
    <template #cell-assessorsCounted="{ item }">{{ item.average?.memberCount ?? '' }}</template>
    <template #cell-manualAverage="{ item }"
      ><Badge v-if="item.average" :variant="item.average.isManual ? 'warning' : 'muted'">{{
        item.average.isManual ? t('common.yes') : t('common.no')
      }}</Badge></template
    >
    <template #cell-entityReference="{ item }"
      ><bdi class="u-ltr">{{ item.entity?.reference }}</bdi></template
    >
    <template #cell-open="{ item }">
      <RouterLink
        class="cycle-table__link"
        :to="{
          name: ROUTE_NAMES.AWARD_SECRETARY_REVIEW,
          params: { id: cycleId, assessment: item.id },
        }"
        >{{ t('award.secretary.open') }}</RouterLink
      >
    </template>
  </DataTable>
</template>
