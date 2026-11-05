import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { useTransferSelection } from '@/composables/useTransferSelection'
import { bankTransfersService } from '@/services/bankTransfersService'
import {
  DEFAULT_TRANSFER_SORT,
  TRANSFER_FILTERS,
  TRANSFER_REVIEW_TABS,
} from '@/constants/bankTransfer'
import { PAYMENT_STATUSES } from '@/constants/payments'

const QUEUE_PER_PAGE = 15
const COUNT_PROBE = { perPage: 1 }

function isDecidable(item) {
  return item.status === PAYMENT_STATUSES.UNDER_REVIEW && !item.entity.isFrozen
}

async function totalOf(status) {
  const envelope = await bankTransfersService.list({ ...COUNT_PROBE, 'filter[status]': status })
  return envelope.meta?.pagination?.total ?? 0
}

export function useBankTransferQueue() {
  const list = useFinanceList({
    fetcher: (params) => bankTransfersService.list(params),
    initialFilters: { ...TRANSFER_FILTERS },
    perPage: QUEUE_PER_PAGE,
    sort: DEFAULT_TRANSFER_SORT,
  })
  const selection = useTransferSelection(list.items, { isSelectable: isDecidable })
  const totals = reactive({ paid: null, rejected: null })
  const accounts = ref(new Map())

  const underReviewCount = computed(() => list.meta.value?.counts?.underReview ?? null)
  const tab = computed(() => list.filters.filters.status)
  const canSelect = computed(() => tab.value === TRANSFER_REVIEW_TABS.UNDER_REVIEW)
  const knownAccounts = computed(() => [...accounts.value.values()])

  async function loadTotals() {
    try {
      const [paid, rejected] = await Promise.all([
        totalOf(PAYMENT_STATUSES.PAID),
        totalOf(PAYMENT_STATUSES.REJECTED),
      ])
      totals.paid = paid
      totals.rejected = rejected
    } catch {
      totals.paid = null
      totals.rejected = null
    }
  }

  function rememberAccounts(items) {
    const next = new Map(accounts.value)
    items.forEach((item) => {
      if (item.bankAccount?.id) next.set(item.bankAccount.id, item.bankAccount)
    })
    accounts.value = next
  }

  function setTab(value) {
    selection.clear()
    list.setFilter('status', value)
  }

  async function refresh() {
    selection.clear()
    await Promise.all([list.load(), loadTotals()])
  }

  watch(list.items, rememberAccounts)
  onMounted(loadTotals)

  return {
    ...list,
    selection,
    totals,
    underReviewCount,
    tab,
    canSelect,
    knownAccounts,
    isDecidable,
    setTab,
    refresh,
  }
}
