<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

defineProps({
  cycleId: { type: Number, required: true },
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const columns = computed(() => [
  { key: 'number', label: t('award.secretary.columns.number') },
  { key: 'entity', label: t('award.secretary.columns.entity') },
  { key: 'region', label: t('award.secretary.columns.region') },
  { key: 'status', label: t('award.secretary.columns.status') },
  { key: 'committee', label: t('award.secretary.columns.committee') },
  { key: 'average', label: t('award.secretary.columns.average') },
  { key: 'open', label: t('award.secretary.columns.open') },
])
</script>

<template>
  <DataTable
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
      <bdi v-if="item.average" class="u-ltr secretary-table__average"
        >{{ item.average.percent }}%</bdi
      >
      <span v-else class="committee-member__note">{{ t('award.secretary.noAverage') }}</span>
    </template>
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
