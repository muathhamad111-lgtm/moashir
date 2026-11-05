import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { paymentsService } from '@/services/paymentsService'
import { toCamelKey } from '@/api/caseMapping'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { usePortalTab } from '@/composables/usePortalTab'
import {
  MY_PAYMENT_TAB_STATUSES,
  MY_PAYMENT_TABS,
  PAYMENT_CHANNELS,
  PAYMENT_STATUSES,
  PENDING_ORDER_LIMIT,
} from '@/constants/payments'

const DEFAULT_PER_PAGE = 15

function countKeyOf(status) {
  return toCamelKey(toCanonicalStatus(status))
}

function sumOf(counts, statuses) {
  if (!counts) return null
  return statuses.reduce((sum, status) => sum + (counts[countKeyOf(status)] ?? 0), 0)
}

function isOpenOrder(payment, now = Date.now()) {
  return (
    payment.channel !== PAYMENT_CHANNELS.BANK_TRANSFER &&
    payment.status === PAYMENT_STATUSES.CREATED &&
    Boolean(payment.expiresAt) &&
    new Date(payment.expiresAt).getTime() > now
  )
}

function tabQuery(tab, page, perPage) {
  const statuses = MY_PAYMENT_TAB_STATUSES[tab]
  return statuses.length
    ? { 'filter[status]': statuses.join(','), page, perPage }
    : { page, perPage }
}

export function usePaymentsHub({ perPage = DEFAULT_PER_PAGE } = {}) {
  const { t } = useI18n()
  const { tab } = usePortalTab(Object.values(MY_PAYMENT_TABS))
  const rows = ref([])
  const pendingOrders = ref([])
  const stats = ref(null)
  const statusCounts = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')
  const page = ref(1)
  const lastPage = ref(1)

  const allStatuses = Object.values(PAYMENT_STATUSES)
  const tabs = computed(() =>
    Object.values(MY_PAYMENT_TABS).map((value) => ({
      value,
      label: t(`portal.payments.tabs.${value}`),
      count: sumOf(
        statusCounts.value,
        value === MY_PAYMENT_TABS.ALL ? allStatuses : MY_PAYMENT_TAB_STATUSES[value],
      ),
    })),
  )
  const pendingIds = computed(() => pendingOrders.value.map((order) => order.id))
  const tableRows = computed(() => rows.value.filter((row) => !pendingIds.value.includes(row.id)))
  const hasTestRows = computed(() => rows.value.some((row) => row.isTest))
  const isEmpty = computed(() => !tableRows.value.length && !pendingOrders.value.length)

  async function loadSummary() {
    const { meta } = await paymentsService.listMine({ perPage: 1 })
    stats.value = meta?.stats ?? null
    statusCounts.value = meta?.statusCounts ?? null
  }

  async function loadPendingOrders() {
    const { data } = await paymentsService.listMine({
      'filter[status]': PAYMENT_STATUSES.CREATED,
      perPage: PENDING_ORDER_LIMIT,
    })
    pendingOrders.value = (data ?? []).filter((payment) => isOpenOrder(payment))
  }

  async function loadRows() {
    const { data, meta } = await paymentsService.listMine(tabQuery(tab.value, page.value, perPage))
    rows.value = data ?? []
    lastPage.value = meta?.pagination?.lastPage ?? 1
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      await Promise.all([loadSummary(), loadPendingOrders(), loadRows()])
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function reloadRows() {
    status.value = 'loading'
    try {
      await loadRows()
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function setPage(next) {
    if (next < 1 || next > lastPage.value || next === page.value) return
    page.value = next
  }

  function dropExpiredOrder(id) {
    pendingOrders.value = pendingOrders.value.filter((order) => order.id !== id)
    load()
  }

  watch(tab, () => {
    if (page.value === 1) reloadRows()
    else page.value = 1
  })
  watch(page, reloadRows)
  onMounted(load)

  return {
    tab,
    tabs,
    stats,
    statusCounts,
    pendingOrders,
    tableRows,
    hasTestRows,
    isEmpty,
    status,
    errorMessage,
    page,
    lastPage,
    load,
    setPage,
    dropExpiredOrder,
  }
}
