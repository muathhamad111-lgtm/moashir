<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import CycleStatusBadge from './CycleStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const columns = computed(() => [
  { key: 'name', label: t('cycles.columns.name') },
  { key: 'status', label: t('cycles.columns.status') },
  { key: 'submission', label: t('cycles.columns.submission') },
  { key: 'regions', label: t('cycles.columns.regions') },
  { key: 'counts', label: t('cycles.columns.requests') },
])
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('cycles.list.emptyTitle')"
    :empty-message="t('cycles.list.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-name="{ item }">
      <RouterLink
        class="cycle-table__link"
        :to="{ name: ROUTE_NAMES.AWARD_CYCLE, params: { id: item.id } }"
      >
        {{ localizedText(item.name, locale) }} <bdi class="u-ltr">{{ item.year }}</bdi>
      </RouterLink>
    </template>
    <template #cell-status="{ item }"><CycleStatusBadge :status="item.status" /></template>
    <template #cell-submission="{ item }">
      <span class="cycle-table__window">
        <DateStamp :value="item.submissionStart" :with-time="false" />
        <span aria-hidden="true">–</span>
        <DateStamp :value="item.submissionEnd" :with-time="false" />
      </span>
    </template>
    <template #cell-regions="{ item }">{{
      t('cycles.list.regionCount', { count: item.regions.length }, item.regions.length)
    }}</template>
    <template #cell-counts="{ item }">
      <span v-if="item.counts" class="cycle-table__counts">
        {{
          t('cycles.list.counts', {
            requests: item.counts.requests,
            qualified: item.counts.qualified,
          })
        }}
      </span>
    </template>
  </DataTable>
</template>
