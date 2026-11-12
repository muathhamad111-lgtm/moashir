import { computed } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { financeService } from '@/services/financeService'
import { DEFAULT_TRANSACTION_SORT } from '@/constants/finance'

export const TRANSACTION_FILTERS = {
  status: '',
  purpose: '',
  channel: '',
  method: '',
  from: '',
  to: '',
  mode: '',
  entity_id: '',
}

export function useTransactions() {
  const list = useFinanceList({
    fetcher: (params) => financeService.listTransactions(params),
    initialFilters: { ...TRANSACTION_FILTERS },
    sort: DEFAULT_TRANSACTION_SORT,
  })
  const stats = computed(() => list.meta.value.stats ?? null)
  const exportParams = computed(() => list.filters.toParams())
  return { ...list, stats, exportParams }
}
