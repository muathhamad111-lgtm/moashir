<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import PaymentEventsTable from './PaymentEventsTable.vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { financeService } from '@/services/financeService'
import { FINANCE_MODES, PAYMENT_EVENT_RESULTS, PAYMENT_EVENT_SOURCES } from '@/constants/finance'

// The payment events log: what the gateway and the verification jobs reported.
const { t } = useI18n()
const { items, status, errorMessage, load, filters, paginator, searchInput, setFilter } =
  useFinanceList({
    fetcher: (params) => financeService.listPaymentEvents(params),
    initialFilters: { source: '', result: '', mode: '' },
    perPage: 15,
  })
const { pagination, setPage } = paginator

function optionsOf(values, prefix) {
  return [
    { value: '', label: t('dataList.filter.all') },
    ...values.map((value) => ({ value, label: t(`${prefix}.${value}`) })),
  ]
}

const sourceOptions = computed(() => optionsOf(PAYMENT_EVENT_SOURCES, 'finance.events.sources'))
const resultOptions = computed(() => optionsOf(PAYMENT_EVENT_RESULTS, 'finance.events.results'))
const modeOptions = computed(() => optionsOf(Object.values(FINANCE_MODES), 'finance.filters.modes'))
const filterState = { filters: filters.filters, setFilter }
</script>

<template>
  <div class="finance-tab">
    <ListToolbar
      v-model:search="searchInput"
      :search-placeholder="t('finance.events.searchPlaceholder')"
      :filter-state="filterState"
    >
      <template #filters="{ draft }">
        <AppSelect
          :model-value="draft.filters.source"
          :options="sourceOptions"
          :label="t('finance.events.source')"
          @update:model-value="draft.setFilter('source', $event)"
        />
        <AppSelect
          :model-value="draft.filters.result"
          :options="resultOptions"
          :label="t('finance.events.result')"
          @update:model-value="draft.setFilter('result', $event)"
        />
        <AppSelect
          :model-value="draft.filters.mode"
          :options="modeOptions"
          :label="t('finance.events.mode')"
          @update:model-value="draft.setFilter('mode', $event)"
        />
      </template>
    </ListToolbar>
    <p class="finance-page__note" role="note">{{ t('finance.events.noPayload') }}</p>
    <PaymentEventsTable
      :items="items"
      :status="status"
      :error-message="errorMessage"
      @retry="load"
    />
    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      @update:page="setPage($event)"
    />
  </div>
</template>
