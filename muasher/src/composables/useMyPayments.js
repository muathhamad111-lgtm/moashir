import { computed } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { paymentsService } from '@/services/paymentsService'

export function useMyPayments() {
  const list = useFinanceList({
    fetcher: (params) => paymentsService.listMine(params),
    initialFilters: { status: '', purpose: '' },
  })
  const stats = computed(() => list.meta.value.stats ?? null)
  return { ...list, stats }
}
