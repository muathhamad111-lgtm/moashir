<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import ThresholdBadge from './ThresholdBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { FILTERING_OUTCOMES } from '@/constants/advanced'
import { COLUMN_KINDS } from '@/utils/tableColumns'
import { localizedText } from '@/utils/localizedText'
import { pointsText, twoDecimals } from '@/utils/percentText'

const props = defineProps({
  cycleId: { type: Number, required: true },
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()
const EVENT_DATES = ['submittedAt', 'initialAssessedAt']

const columns = computed(() => [
  { key: 'number', label: t('filtering.columns.number') },
  { key: 'entity', label: t('filtering.columns.entity') },
  { key: 'region', label: t('filtering.columns.region') },
  { key: 'percent', label: t('filtering.columns.percent') },
  { key: 'threshold', label: t('filtering.columns.threshold') },
  { key: 'status', label: t('filtering.columns.status') },
  ...EVENT_DATES.map((key) => ({ key, label: t(`filtering.columns.${key}`) })),
  { key: 'dueAt', label: t('filtering.columns.dueAt') },
  { key: 'score', label: t('filtering.columns.score'), kind: COLUMN_KINDS.NUMBER },
  {
    key: 'entityReference',
    label: t('filtering.columns.entityReference'),
    kind: COLUMN_KINDS.CODE,
  },
  { key: 'decision', label: t('filtering.columns.decision'), kind: COLUMN_KINDS.STATUS },
])

function detailRoute(item) {
  return {
    name: ROUTE_NAMES.AWARD_FILTERING_DETAIL,
    params: { id: props.cycleId, assessment: item.id },
  }
}
</script>

<template>
  <DataTable
    table-key="award-filtering"
    stacked
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('filtering.queue.emptyTitle')"
    :empty-message="t('filtering.queue.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-number="{ item }">
      <RouterLink class="cycle-table__link" :to="detailRoute(item)"
        ><bdi dir="ltr">{{ item.number }}</bdi></RouterLink
      >
    </template>
    <template #cell-entity="{ item }">{{ localizedText(item.entity?.name, locale) }}</template>
    <template #cell-region="{ item }">{{ localizedText(item.region?.name, locale) }}</template>
    <template #cell-percent="{ item }">
      <bdi class="filtering-table__percent u-ltr">{{
        item.initialResult
          ? t('filtering.percent', { value: twoDecimals(item.initialResult.percent) })
          : t('accredited.common.notSet')
      }}</bdi>
    </template>
    <template #cell-threshold="{ item }"><ThresholdBadge :meets="item.meetsThreshold" /></template>
    <template #cell-status="{ item }">
      <span class="filtering-table__status">
        <StatusBadge :status="item.status" />
        <Badge v-if="item.decision?.isOverride" variant="warning">{{
          t('filtering.override.badge')
        }}</Badge>
      </span>
    </template>
    <template v-for="key in EVENT_DATES" :key="key" #[`cell-${key}`]="{ item }"
      ><DateStamp v-if="item[key]" :value="item[key]" :with-time="false"
    /></template>
    <template #cell-dueAt="{ item }">
      <span v-if="item.dueAt" class="committee-work__badges">
        <DateStamp :value="item.dueAt" :with-time="false" />
        <OverdueBadge v-if="item.isOverdue" :overdue="true" />
      </span>
    </template>
    <template #cell-score="{ item }"
      ><bdi v-if="item.initialResult" class="u-ltr">{{
        t('filtering.scoreOf', {
          total: pointsText(item.initialResult.total),
          maxTotal: pointsText(item.initialResult.maxTotal),
        })
      }}</bdi></template
    >
    <template #cell-entityReference="{ item }"
      ><bdi class="u-ltr">{{ item.entity?.reference }}</bdi></template
    >
    <template #cell-decision="{ item }">
      <span v-if="item.decision" class="committee-work__badges">
        <Badge
          :variant="item.decision.outcome === FILTERING_OUTCOMES.QUALIFIED ? 'success' : 'danger'"
          >{{ t(`filtering.outcomes.${item.decision.outcome}`) }}</Badge
        >
        <DateStamp :value="item.decision.decidedAt" :with-time="false" />
      </span>
    </template>
  </DataTable>
</template>
