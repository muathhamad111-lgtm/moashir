<script setup>
import { ROUTE_NAMES } from '@/constants/routeNames'
import BaseButton from '@/components/ui/BaseButton.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import PaymentEventsTable from '@/components/finance/PaymentEventsTable.vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { financeService } from '@/services/financeService'
import { FINANCE_MODES, PAYMENT_EVENT_RESULTS, PAYMENT_EVENT_SOURCES } from '@/constants/finance'

const { t } = useI18n()
const { items, status, errorMessage, load, filters, paginator, searchInput, setFilter } =
  useFinanceList({
    fetcher: (params) => financeService.listPaymentEvents(params),
    initialFilters: { source: '', result: '', mode: '' },
    perPage: 15,
  })
const { pagination, setPage } = paginator

useHead({ title: () => t('finance.events.title') })

function optionsOf(allKey, values, prefix) {
  return [
    { value: '', label: t(allKey) },
    ...values.map((value) => ({ value, label: t(`${prefix}.${value}`) })),
  ]
}

const sourceOptions = computed(() =>
  optionsOf('finance.events.allSources', PAYMENT_EVENT_SOURCES, 'finance.events.sources'),
)
const resultOptions = computed(() =>
  optionsOf('finance.events.allResults', PAYMENT_EVENT_RESULTS, 'finance.events.results'),
)
const modeOptions = computed(() =>
  optionsOf('finance.events.allModes', Object.values(FINANCE_MODES), 'finance.filters.modes'),
)
const filterState = { filters: filters.filters, setFilter }
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader
      :eyebrow="t('finance.eyebrow')"
      :title="t('finance.events.title')"
      :subtitle="t('finance.events.subtitle')"
    >
      <template #actions>
        <BaseButton
          variant="secondary"
          icon="arrow-left"
          :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTIONS }"
        >
          {{ t('finance.operations.backToTransactions') }}
        </BaseButton>
      </template>
    </FinancePageHeader>
    <Card>
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
    </Card>
  </section>
</template>
