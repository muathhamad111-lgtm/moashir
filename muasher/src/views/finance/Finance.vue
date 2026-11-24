<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import FinanceOverviewCards from '@/components/finance/FinanceOverviewCards.vue'
import FinanceOperationsPanel from '@/components/finance/FinanceOperationsPanel.vue'
import PendingTransfersBanner from '@/components/finance/PendingTransfersBanner.vue'
import TransferQueuePanel from '@/components/finance/TransferQueuePanel.vue'
import TransactionReceiptModal from '@/components/finance/TransactionReceiptModal.vue'
import TransferReviewModal from '@/components/finance/TransferReviewModal.vue'
import ApproveTransferDialog from '@/components/finance/ApproveTransferDialog.vue'
import RejectTransferDialog from '@/components/finance/RejectTransferDialog.vue'
import { useTransactions } from '@/composables/useTransactions'
import { useFinanceCardStats } from '@/composables/useFinanceCardStats'
import { usePendingTransfersCount } from '@/composables/usePendingTransfersCount'
import { useTransactionRowActions } from '@/composables/useTransactionRowActions'
import { useTransferDecisions } from '@/composables/useTransferDecisions'
import { usePermissions } from '@/composables/usePermissions'
import { PAYMENT_CHANNELS } from '@/constants/payments'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { accessFor } from '@/constants/routeAccess'
import {
  TRANSACTION_ROW_ACTIONS as A,
  isPendingTransfer,
} from '@/utils/finance/transactionRowActions'

// «المالية»: one list of every operation, newest first. The status cards filter it; each row offers
// the actions its status allows. Filtered to the transfers waiting for approval, the list is the
// review queue (with its bulk decisions and proof checks), which is also all a reviewer without the
// operations permission sees.
const props = defineProps({
  // Opened from the old «بانتظار الاعتماد» address: the list starts on the pending transfers.
  pending: { type: Boolean, default: false },
})

const PENDING_FILTER = Object.freeze({
  status: 'under_review',
  type: PAYMENT_CHANNELS.BANK_TRANSFER,
})
const NO_FILTER = Object.freeze({ status: '', type: '' })

const { t } = useI18n()
const router = useRouter()
const { canAccess } = usePermissions()

const seesOperations = canAccess(accessFor(ROUTE_NAMES.FINANCE_TRANSACTIONS))
const seesTransfers = canAccess(accessFor(ROUTE_NAMES.FINANCE_TRANSFERS))
const operations = seesOperations ? useTransactions() : null
const cards = operations ? useFinanceCardStats(operations) : null
const pendingCount = usePendingTransfersCount({ enabled: seesTransfers })
const { actionsFor } = useTransactionRowActions()
const decisions = useTransferDecisions({ onDecided: refresh })
const viewing = ref(null)
const reviewing = ref(null)

useHead({ title: () => t('finance.page.title') })

const activeFilter = computed(() => ({
  status: operations?.filters.filters.status ?? '',
  type: operations?.filters.filters.type ?? '',
}))
const showingPending = computed(
  () =>
    !operations ||
    (activeFilter.value.status === PENDING_FILTER.status &&
      activeFilter.value.type === PENDING_FILTER.type),
)

const showsQueue = computed(() => showingPending.value && seesTransfers)

function applyFilter({ status, type }) {
  operations.setFilter('type', type)
  operations.setFilter('status', status)
}

// A card toggles its filter: a second click on the same status shows every operation again.
function toggleFilter(next) {
  const same = activeFilter.value.status === next.status && activeFilter.value.type === next.type
  applyFilter(same ? NO_FILTER : next)
}

if (props.pending && operations) applyFilter(PENDING_FILTER)

function refresh() {
  pendingCount.load()
  operations?.load()
}

function openOnPage(item, action) {
  router.push({ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: item.id }, query: { action } })
}

const ROW_ACTIONS = {
  [A.VIEW]: (item) => {
    if (isPendingTransfer(item) && seesTransfers) reviewing.value = item.id
    else viewing.value = item.id
  },
  [A.APPROVE]: (item) => decisions.openApprove([item]),
  [A.REJECT]: (item) => decisions.openReject([item]),
  [A.REFUND]: (item) => openOnPage(item, 'refund'),
  [A.SETTLE]: (item) => openOnPage(item, 'settle'),
}

function decideFromReview(kind, transfer) {
  reviewing.value = null
  if (kind === 'approve') decisions.openApprove([transfer])
  else decisions.openReject([transfer])
}
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader
      :eyebrow="t('finance.eyebrow')"
      :title="t('finance.page.title')"
      :subtitle="t('finance.page.subtitle')"
    />

    <PendingTransfersBanner
      v-if="pendingCount.count.value"
      :count="pendingCount.count.value"
      :showing="showingPending"
      @show="applyFilter(PENDING_FILTER)"
    />

    <FinanceOverviewCards
      v-if="operations"
      :stats="cards.stats.value"
      :loading="operations.status.value === 'loading' && !cards.stats.value"
      :active="activeFilter"
      @select="toggleFilter"
    />

    <p v-if="decisions.conflict.value" class="transfers-page__conflict" role="alert">
      <AppIcon name="warning-triangle" />
      {{ t(`finance.transfers.conflict.${decisions.conflict.value.replace('.', '_')}`) }}
    </p>

    <Card class="finance-page__list">
      <TransferQueuePanel v-if="showsQueue" @decided="refresh" />
      <FinanceOperationsPanel
        v-else
        :operations="operations"
        :actions-for="actionsFor"
        @action="ROW_ACTIONS[$event.key]?.($event.item)"
      />
    </Card>

    <TransactionReceiptModal
      v-if="viewing"
      :key="viewing"
      :model-value="true"
      :payment-id="viewing"
      @update:model-value="viewing = null"
    />
    <TransferReviewModal
      v-if="reviewing"
      :key="reviewing"
      :model-value="true"
      :payment-id="reviewing"
      @update:model-value="reviewing = null"
      @approve="decideFromReview('approve', $event)"
      @reject="decideFromReview('reject', $event)"
    />
    <ApproveTransferDialog
      v-model="decisions.approveOpen.value"
      :targets="decisions.approveTargets.value"
      :asks-reason="decisions.asksReason.value"
      :action="decisions.approve"
    />
    <RejectTransferDialog
      v-model="decisions.rejectOpen.value"
      :targets="decisions.rejectTargets.value"
      :asks-reason="decisions.asksReason.value"
      :action="decisions.reject"
    />
  </section>
</template>
