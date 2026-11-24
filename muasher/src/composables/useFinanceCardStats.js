import { computed, ref, watch } from 'vue'
import { financeService } from '@/services/financeService'
import { listFilterParams } from '@/utils/finance/transactionFilterValues'
import { logger } from '@/utils/logger'

const CARD_FILTERS = ['filter[status]', 'filter[type]']

// The status cards keep counting every status while one of them filters the list. Without a card
// filter they read the list's own figures; with one, the same query without its status and
// channel (search, dates and entity still apply).
export function useFinanceCardStats(operations) {
  const stats = ref(null)
  const cardFilterOn = computed(() =>
    Boolean(operations.filters.filters.status || operations.filters.filters.type),
  )

  async function loadWithoutCardFilter() {
    const params = { ...operations.filters.toParams(), page: 1, per_page: 1 }
    CARD_FILTERS.forEach((key) => delete params[key])
    try {
      const envelope = await financeService.listTransactions(listFilterParams(params))
      stats.value = envelope.meta?.stats ?? stats.value
    } catch (error) {
      // The cards keep the last figures they showed; the list itself reports the failure.
      logger.breadcrumb('finance', 'card-stats.failed', { message: error?.message })
    }
  }

  watch(operations.meta, () => {
    if (cardFilterOn.value) loadWithoutCardFilter()
    else stats.value = operations.stats.value
  })

  return { stats }
}
