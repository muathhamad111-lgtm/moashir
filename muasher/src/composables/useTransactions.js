import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useFinanceList } from '@/composables/useFinanceList'
import { financeService } from '@/services/financeService'
import { DEFAULT_TRANSACTION_SORT } from '@/constants/finance'
import { listFilterParams, listFiltersFromQuery } from '@/utils/finance/transactionFilterValues'

export const TRANSACTION_FILTERS = {
  type: '',
  status: '',
  purpose: '',
  from: '',
  to: '',
  entity_id: '',
  mode: '',
}

export function useTransactions() {
  const list = useFinanceList({
    fetcher: (params) => financeService.listTransactions(listFilterParams(params)),
    initialFilters: { ...TRANSACTION_FILTERS, ...listFiltersFromQuery(useRoute()?.query) },
    sort: DEFAULT_TRANSACTION_SORT,
  })
  const stats = computed(() => list.meta.value.stats ?? null)
  const exportParams = computed(() => listFilterParams(list.filters.toParams()))
  return { ...list, stats, exportParams }
}
