<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import ColumnChooser from '@/components/data-list/ColumnChooser.vue'
import TransactionFilters from './TransactionFilters.vue'
import TransactionsTable from './TransactionsTable.vue'
import { useTransactionsExport } from '@/composables/useTransactionsExport'
import { useColumnPreferences } from '@/composables/useColumnPreferences'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { TRANSACTION_COLUMNS } from '@/constants/finance'
import { transactionColumns } from '@/utils/finance/transactionColumns'

// The «العمليات» tab: every operation, searchable, filterable, sortable and exportable.
const props = defineProps({
  operations: { type: Object, required: true },
})

const emit = defineEmits(['view'])
const { t } = useI18n()
const { can } = usePermissions()
const exporter = useTransactionsExport()
const columns = useColumnPreferences({
  storageKey: TRANSACTION_COLUMNS.STORAGE_KEY,
  toggleable: TRANSACTION_COLUMNS.TOGGLEABLE,
  hiddenByDefault: TRANSACTION_COLUMNS.HIDDEN_BY_DEFAULT,
})
const {
  items,
  status,
  errorMessage,
  load,
  filters,
  paginator,
  searchInput,
  exportParams,
  setFilter,
  setSort,
} = props.operations
const { pagination, setPage } = paginator

const chooserColumns = computed(() =>
  transactionColumns(t).filter((column) => TRANSACTION_COLUMNS.TOGGLEABLE.includes(column.key)),
)
const filterState = { filters: filters.filters, setFilter, sort: filters.sort, setSort }
</script>

<template>
  <div class="finance-tab">
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
      <template #export>
        <ColumnChooser
          :columns="chooserColumns"
          :visible="columns.visible.value"
          @toggle="columns.setVisible"
          @reset="columns.reset"
        />
        <BaseButton
          v-if="can(PERMISSIONS.FINANCE_OPERATIONS_EXPORT)"
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
      :sort="filters.sort.value"
      :is-visible="columns.isVisible"
      @retry="load"
      @sort="setSort"
      @view="emit('view', $event)"
    />
    <Pagination
      v-if="status === 'success' && pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      @update:page="setPage($event)"
    />
  </div>
</template>
