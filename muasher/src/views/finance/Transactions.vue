<script setup>
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import FinanceNav from '@/components/finance/FinanceNav.vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import FinanceStatsCards from '@/components/finance/FinanceStatsCards.vue'
import TransactionFilters from '@/components/finance/TransactionFilters.vue'
import TransactionsTable from '@/components/finance/TransactionsTable.vue'
import { useTransactions } from '@/composables/useTransactions'
import { useTransactionsExport } from '@/composables/useTransactionsExport'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'

const { t } = useI18n()
const { can } = usePermissions()
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

useHead({ title: () => t('finance.transactions.title') })
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader
      :eyebrow="t('finance.eyebrow')"
      :title="t('finance.transactions.title')"
      :subtitle="t('finance.transactions.subtitle')"
    >
      <template v-if="can(PERMISSIONS.FINANCE_OPERATIONS_EXPORT)" #actions>
        <BaseButton
          variant="secondary"
          icon="doc"
          :loading="exporter.exporting.value"
          :disabled="status !== 'success'"
          @click="exporter.run(exportParams)"
        >
          {{ t('finance.export.action') }}
        </BaseButton>
      </template>
    </FinancePageHeader>
    <FinanceNav />

    <FinanceStatsCards :stats="stats" :loading="status === 'loading' && !stats" />
    <p class="finance-page__note" role="note">{{ t('finance.transactions.statsLiveOnly') }}</p>

    <Card>
      <ListToolbar
        v-model:search="searchInput"
        :search-placeholder="t('finance.transactions.searchPlaceholder')"
      >
        <template #filters>
          <TransactionFilters
            :filters="filters.filters"
            :sort="filters.sort.value"
            @filter="setFilter"
            @sort="setSort"
          />
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
