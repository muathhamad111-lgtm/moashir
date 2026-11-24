<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataListCard from '@/components/data-list/DataListCard.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import TransferQueueFilters from './TransferQueueFilters.vue'
import TransferQueueTable from './TransferQueueTable.vue'
import TransferBulkBar from './TransferBulkBar.vue'
import BulkOutcomeNotice from './BulkOutcomeNotice.vue'
import ApproveTransferDialog from './ApproveTransferDialog.vue'
import RejectTransferDialog from './RejectTransferDialog.vue'
import TransferReviewModal from './TransferReviewModal.vue'
import { useBankTransferQueue } from '@/composables/useBankTransferQueue'
import { useTransferDecisions } from '@/composables/useTransferDecisions'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'

// The «بانتظار الاعتماد» tab: bank transfers under review. Reviewers decide a row in place or
// from its review window; every decision goes through the confirmation or the reason dialog.
const emit = defineEmits(['decided'])
const { t } = useI18n()
const { can } = usePermissions()
const queue = useBankTransferQueue()
const { items, status, errorMessage, filters, paginator, searchInput, selection } = queue
const { pagination, setPage } = paginator
const decisions = useTransferDecisions({
  onDecided: () => {
    queue.refresh()
    emit('decided')
  },
})
const reviewing = ref(null)

const isReviewer = computed(() => can(PERMISSIONS.FINANCE_TRANSFERS_REVIEW))
const selectable = computed(() => isReviewer.value && queue.canSelect.value)
const references = computed(() =>
  Object.fromEntries(items.value.map((item) => [item.id, item.reference])),
)
const filterState = {
  filters: filters.filters,
  setFilter: queue.setFilter,
  sort: filters.sort,
  setSort: queue.setSort,
}

function decideFromReview(kind, transfer) {
  reviewing.value = null
  if (kind === 'approve') decisions.openApprove([transfer])
  else decisions.openReject([transfer])
}
</script>

<template>
  <div class="finance-tab transfers-page">
    <p v-if="!isReviewer" class="transfers-page__read-only" role="note">
      <AppIcon name="lock" />
      {{ t('finance.transfers.readOnly') }}
    </p>
    <DataListCard>
      <template #toolbar>
        <ListToolbar
          v-model:search="searchInput"
          :search-placeholder="t('finance.transfers.searchPlaceholder')"
          :filter-state="filterState"
        >
          <template #filters="{ draft }">
            <TransferQueueFilters
              :filters="draft.filters"
              :sort="draft.sort.value"
              :accounts="queue.accounts.value"
              @filter="draft.setFilter"
              @sort="draft.setSort"
            />
          </template>
        </ListToolbar>
      </template>

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
        :can-decide="isReviewer"
        :empty-title="t('finance.transfers.empty.underReview.title')"
        :empty-message="t('finance.transfers.empty.underReview.message')"
        @retry="queue.load"
        @view="reviewing = $event.id"
        @approve="decisions.openApprove([$event])"
        @reject="decisions.openReject([$event])"
      />
      <template #footer>
        <Pagination
          v-if="status === 'success' && pagination.lastPage > 1"
          :page="pagination.page"
          :last-page="pagination.lastPage"
          @update:page="setPage($event)"
        />
      </template>
    </DataListCard>

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
  </div>
</template>
