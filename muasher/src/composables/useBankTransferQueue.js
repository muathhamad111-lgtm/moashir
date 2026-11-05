import { computed, onMounted, ref } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { useTransferSelection } from '@/composables/useTransferSelection'
import { bankTransfersService } from '@/services/bankTransfersService'
import {
  DEFAULT_TRANSFER_SORT,
  TRANSFER_ACCOUNTS_PER_PAGE,
  TRANSFER_FILTERS,
  TRANSFER_REVIEW_TABS,
} from '@/constants/bankTransfer'
import { PAYMENT_STATUSES } from '@/constants/payments'

const QUEUE_PER_PAGE = 15

function isDecidable(item) {
  return item.status === PAYMENT_STATUSES.UNDER_REVIEW && !item.entity.isFrozen
}

function countOf(value) {
  return Number.isInteger(value) ? value : null
}

export function useBankTransferQueue() {
  const list = useFinanceList({
    fetcher: (params) => bankTransfersService.list(params),
    initialFilters: { ...TRANSFER_FILTERS },
    perPage: QUEUE_PER_PAGE,
    sort: DEFAULT_TRANSFER_SORT,
  })
  const selection = useTransferSelection(list.items, { isSelectable: isDecidable })
  const accounts = ref([])

  const counts = computed(() => {
    const raw = list.meta.value?.counts ?? {}
    return {
      underReview: countOf(raw.underReview),
      approvedToday: countOf(raw.approvedToday),
      rejectedToday: countOf(raw.rejectedToday),
    }
  })
  const underReviewCount = computed(() => counts.value.underReview)
  const tab = computed(() => list.filters.filters.status)
  const canSelect = computed(() => tab.value === TRANSFER_REVIEW_TABS.UNDER_REVIEW)

  async function loadAccounts() {
    try {
      const envelope = await bankTransfersService.listAccounts({
        perPage: TRANSFER_ACCOUNTS_PER_PAGE,
      })
      accounts.value = envelope.data
    } catch {
      accounts.value = []
    }
  }

  function setTab(value) {
    selection.clear()
    list.setFilter('status', value)
  }

  async function refresh() {
    selection.clear()
    await list.load()
  }

  onMounted(loadAccounts)

  return {
    ...list,
    selection,
    counts,
    underReviewCount,
    accounts,
    tab,
    canSelect,
    isDecidable,
    setTab,
    refresh,
  }
}
