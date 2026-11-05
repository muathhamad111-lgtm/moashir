<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import FinanceNav from '@/components/finance/FinanceNav.vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import TransferQueueFilters from '@/components/finance/TransferQueueFilters.vue'
import TransferQueueTable from '@/components/finance/TransferQueueTable.vue'
import TransferBulkBar from '@/components/finance/TransferBulkBar.vue'
import BulkOutcomeNotice from '@/components/finance/BulkOutcomeNotice.vue'
import ApproveTransferDialog from '@/components/finance/ApproveTransferDialog.vue'
import RejectTransferDialog from '@/components/finance/RejectTransferDialog.vue'
import { useBankTransferQueue } from '@/composables/useBankTransferQueue'
import { useTransferDecisions } from '@/composables/useTransferDecisions'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { TRANSFER_REVIEW_TABS } from '@/constants/bankTransfer'
import { formatNumber } from '@/utils/formatNumber'

const { t, n } = useI18n()
const { can } = usePermissions()
const queue = useBankTransferQueue()
const { items, status, errorMessage, filters, paginator, searchInput, selection } = queue
const { pagination, setPage } = paginator
const decisions = useTransferDecisions({ onDecided: () => queue.refresh() })

useHead({ title: () => t('finance.transfers.title') })

const isReviewer = computed(() => can(PERMISSIONS.FINANCE_TRANSFERS_REVIEW))
const selectable = computed(() => isReviewer.value && queue.canSelect.value)

function countLabel(value) {
  return value === null ? t('finance.common.none') : formatNumber(n, value)
}

const stats = computed(() => [
  {
    id: 'under-review',
    icon: 'refresh-dots',
    value: countLabel(queue.underReviewCount.value),
    label: t('finance.transfers.stats.underReview'),
  },
  {
    id: 'approved',
    icon: 'check-circle',
    value: countLabel(queue.totals.paid),
    label: t('finance.transfers.stats.approved'),
  },
  {
    id: 'rejected',
    icon: 'x',
    value: countLabel(queue.totals.rejected),
    label: t('finance.transfers.stats.rejected'),
  },
])

const tabs = computed(() =>
  Object.values(TRANSFER_REVIEW_TABS).map((value) => ({
    value,
    label:
      value === TRANSFER_REVIEW_TABS.UNDER_REVIEW && queue.underReviewCount.value !== null
        ? t('finance.transfers.tabs.underReviewCount', {
            count: formatNumber(n, queue.underReviewCount.value),
          })
        : t(`finance.transfers.tabs.${value}`),
  })),
)

const references = computed(() =>
  Object.fromEntries(items.value.map((item) => [item.id, item.reference])),
)
const emptyKey = computed(() =>
  queue.tab.value === TRANSFER_REVIEW_TABS.UNDER_REVIEW ? 'underReview' : 'other',
)
</script>

<template>
  <section class="finance-page transfers-page">
    <FinancePageHeader
      :eyebrow="t('finance.eyebrow')"
      :title="t('finance.transfers.title')"
      :subtitle="t('finance.transfers.subtitle')"
    />
    <FinanceNav />

    <StatsCards
      :stats="stats"
      :loading="status === 'loading' && queue.underReviewCount.value === null"
      :skeleton-count="3"
    />

    <p v-if="!isReviewer" class="transfers-page__read-only" role="note">
      <AppIcon name="lock" />
      {{ t('finance.transfers.readOnly') }}
    </p>

    <Card>
      <Tabs :model-value="queue.tab.value" :tabs="tabs" @update:model-value="queue.setTab">
        <div class="transfers-page__panel">
          <ListToolbar
            v-model:search="searchInput"
            :search-placeholder="t('finance.transfers.searchPlaceholder')"
          >
            <template #filters>
              <TransferQueueFilters
                :filters="filters.filters"
                :sort="filters.sort.value"
                :accounts="queue.knownAccounts.value"
                @filter="queue.setFilter"
                @sort="queue.setSort"
              />
            </template>
          </ListToolbar>

          <BulkOutcomeNotice
            v-if="decisions.outcome.value"
            :outcome="decisions.outcome.value"
            :references="references"
            @dismiss="decisions.clearOutcome"
          />
          <p v-if="decisions.conflict.value" class="transfers-page__conflict" role="alert">
            <AppIcon name="warning-triangle" />
            {{ t(`finance.transfers.conflict.${decisions.conflict.value.replace('.', '_')}`) }}
          </p>

          <TransferBulkBar
            v-if="selectable && selection.count.value > 0"
            :count="selection.count.value"
            :over-limit="selection.overLimit.value"
            @clear="selection.clear"
            @approve="decisions.openApprove(selection.selected.value)"
            @reject="decisions.openReject(selection.selected.value)"
          />

          <TransferQueueTable
            :items="items"
            :status="status"
            :error-message="errorMessage"
            :selectable="selectable"
            :selection="selection"
            :is-decidable="queue.isDecidable"
            :empty-title="t(`finance.transfers.empty.${emptyKey}.title`)"
            :empty-message="t(`finance.transfers.empty.${emptyKey}.message`)"
            @retry="queue.load"
          />
          <Pagination
            v-if="status === 'success' && pagination.lastPage > 1"
            :page="pagination.page"
            :last-page="pagination.lastPage"
            @update:page="setPage($event)"
          />
        </div>
      </Tabs>
    </Card>

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
