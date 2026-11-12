import { computed, onMounted, ref } from 'vue'
import { paymentsService } from '@/services/paymentsService'
import { useFinanceList } from '@/composables/useFinanceList'
import { PAYMENT_CHANNELS, PAYMENT_STATUSES, PENDING_ORDER_LIMIT } from '@/constants/payments'

const DEFAULT_PER_PAGE = 15

export const MY_PAYMENT_FILTERS = Object.freeze({
  status: '',
  purpose: '',
  channel: '',
  method: '',
  from: '',
  to: '',
})

function isOpenOrder(payment, now = Date.now()) {
  return (
    payment.channel !== PAYMENT_CHANNELS.BANK_TRANSFER &&
    payment.status === PAYMENT_STATUSES.CREATED &&
    Boolean(payment.expiresAt) &&
    new Date(payment.expiresAt).getTime() > now
  )
}

export function usePaymentsHub({ perPage = DEFAULT_PER_PAGE } = {}) {
  const list = useFinanceList({
    fetcher: (params) => paymentsService.listMine(params),
    initialFilters: { ...MY_PAYMENT_FILTERS },
    perPage,
  })
  const pendingOrders = ref([])
  const stats = ref(null)

  const pendingIds = computed(() => pendingOrders.value.map((order) => order.id))
  const tableRows = computed(() =>
    list.items.value.filter((row) => !pendingIds.value.includes(row.id)),
  )
  const activeFilterCount = computed(
    () => Object.values(list.filters.filters).filter((value) => value !== '').length,
  )
  const isFiltered = computed(() => activeFilterCount.value > 0 || list.filters.search.value !== '')

  async function loadSummary() {
    const [pending, overall] = await Promise.all([
      paymentsService.listMine({
        'filter[status]': PAYMENT_STATUSES.CREATED,
        perPage: PENDING_ORDER_LIMIT,
      }),
      paymentsService.listMine({ perPage: 1 }),
    ])
    pendingOrders.value = (pending.data ?? []).filter((payment) => isOpenOrder(payment))
    stats.value = overall.meta?.stats ?? null
  }

  function refresh() {
    loadSummary().catch(() => null)
    list.load()
  }

  function clearFilters() {
    list.paginator.setPage(1)
    list.searchInput.value = ''
    list.filters.reset()
  }

  function dropExpiredOrder(id) {
    pendingOrders.value = pendingOrders.value.filter((order) => order.id !== id)
    refresh()
  }

  onMounted(() => loadSummary().catch(() => null))

  return {
    ...list,
    stats,
    pendingOrders,
    tableRows,
    activeFilterCount,
    isFiltered,
    refresh,
    clearFilters,
    dropExpiredOrder,
  }
}
