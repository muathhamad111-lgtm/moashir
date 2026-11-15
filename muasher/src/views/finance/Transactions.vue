<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import FinanceStatsCards from '@/components/finance/FinanceStatsCards.vue'
import TransactionFilters from '@/components/finance/TransactionFilters.vue'
import TransactionsTable from '@/components/finance/TransactionsTable.vue'
import { useTransactions } from '@/composables/useTransactions'
import { useTransactionsExport } from '@/composables/useTransactionsExport'
import { usePendingTransfersCount } from '@/composables/usePendingTransfersCount'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { accessFor } from '@/constants/routeAccess'
import { formatNumber } from '@/utils/formatNumber'

const { t, n } = useI18n()
const { can, canAccess } = usePermissions()
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  searchInput,
  stats,
  exportParams,
  setFilter,
  setSort,
} = useTransactions()
const { pagination, setPage } = paginator
const exporter = useTransactionsExport()
const canReviewTransfers = canAccess(accessFor(ROUTE_NAMES.FINANCE_TRANSFERS))
const pendingTransfers = usePendingTransfersCount({ enabled: canReviewTransfers })

const transfersLabel = computed(() =>
  pendingTransfers.count.value
    ? t('finance.operations.reviewTransfersCount', {
        count: formatNumber(n, pendingTransfers.count.value),
      })
    : t('finance.operations.reviewTransfers'),
)

useHead({ title: () => t('finance.operations.pageTitle') })
const filterState = { filters: filters.filters, setFilter, sort: filters.sort, setSort }
</script>

<template>
  <section class="finance-page">
    <header class="finance-page__header">
      <h1 class="finance-page__title">{{ t('finance.operations.pageTitle') }}</h1>
      <BaseButton
        v-if="canReviewTransfers"
        variant="secondary"
        icon="inbox"
        :to="{ name: ROUTE_NAMES.FINANCE_TRANSFERS }"
        data-testid="review-transfers"
      >
        {{ transfersLabel }}
      </BaseButton>
    </header>

    <FinanceStatsCards :stats="stats" :loading="status === 'loading' && !stats" />

    <Card>
      <ListToolbar
        v-model:search="searchInput"
        :search-placeholder="t('finance.transactions.searchPlaceholder')"
        :filter-state="filterState"
      >
        <template #filters="{ draft }">
          <TransactionFilters
            :filters="draft.filters"
            :sort="draft.sort.value"
            @filter="draft.setFilter"
            @sort="draft.setSort"
          />
        </template>
        <template v-if="can(PERMISSIONS.FINANCE_OPERATIONS_EXPORT)" #export>
          <BaseButton
            variant="secondary"
            size="sm"
            icon="download"
            :loading="exporter.exporting.value"
            :disabled="status !== 'success'"
            @click="exporter.run(exportParams)"
          >
            {{ t('finance.export.action') }}
          </BaseButton>
        </template>
      </ListToolbar>
      <TransactionsTable
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
