<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Badge from '@/components/ui/Badge.vue'
import ThresholdBadge from './ThresholdBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  cycleId: { type: Number, required: true },
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const columns = computed(() => [
  { key: 'number', label: t('filtering.columns.number') },
  { key: 'entity', label: t('filtering.columns.entity') },
  { key: 'region', label: t('filtering.columns.region') },
  { key: 'percent', label: t('filtering.columns.percent') },
  { key: 'threshold', label: t('filtering.columns.threshold') },
  { key: 'status', label: t('filtering.columns.status') },
])

function detailRoute(item) {
  return { name: ROUTE_NAMES.AWARD_FILTERING_DETAIL, params: { id: props.cycleId, assessment: item.id } }
}
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('filtering.queue.emptyTitle')"
    :empty-message="t('filtering.queue.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-number="{ item }">
      <RouterLink class="cycle-table__link" :to="detailRoute(item)"><bdi dir="ltr">{{ item.number }}</bdi></RouterLink>
    </template>
    <template #cell-entity="{ item }">{{ localizedText(item.entity?.name, locale) }}</template>
    <template #cell-region="{ item }">{{ localizedText(item.region?.name, locale) }}</template>
    <template #cell-percent="{ item }">
      <bdi class="filtering-table__percent u-ltr">{{ item.initialResult ? t('filtering.percent', { value: item.initialResult.percent }) : t('accredited.common.notSet') }}</bdi>
    </template>
    <template #cell-threshold="{ item }"><ThresholdBadge :meets="item.meetsThreshold" /></template>
    <template #cell-status="{ item }">
      <span class="filtering-table__status">
        <StatusBadge :status="item.status" />
        <Badge v-if="item.decision?.isOverride" variant="warning">{{ t('filtering.override.badge') }}</Badge>
      </span>
    </template>
  </DataTable>
</template>
