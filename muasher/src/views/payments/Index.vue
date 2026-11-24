<script setup>
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import PaymentStatTiles from '@/components/payments/portal/PaymentStatTiles.vue'
import PendingOrderCard from '@/components/payments/portal/PendingOrderCard.vue'
import MyPaymentsList from '@/components/payments/portal/MyPaymentsList.vue'
import MyPaymentFilters from '@/components/payments/portal/MyPaymentFilters.vue'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import DataListCard from '@/components/data-list/DataListCard.vue'
import { usePaymentsHub } from '@/composables/usePaymentsHub'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { paymentsService } from '@/services/paymentsService'

const { t } = useI18n()
const hub = usePaymentsHub()
const receipts = useSignedDownload()
const { pagination, setPage } = hub.paginator
const filterState = { filters: hub.filters.filters, setFilter: hub.setFilter }

useHead({ title: () => t('portal.payments.title') })

function downloadReceipt(payment) {
  return receipts.download(payment.id, () => paymentsService.requestReceiptDownload(payment.id))
}
</script>

<template>
  <section class="portal-area payments-hub">
    <PortalAreaHeader
      band
      :eyebrow="t('portal.payments.eyebrow')"
      :title="t('portal.payments.title')"
    />

    <div class="portal-area__body payments-hub__body">
      <PendingOrderCard
        v-for="order in hub.pendingOrders.value"
        :key="order.id"
        :order="order"
        @expired="hub.dropExpiredOrder"
      />

      <PaymentStatTiles v-if="hub.stats.value" :stats="hub.stats.value" />
      <Skeleton v-else-if="hub.status.value === 'loading'" variant="block" />

      <section class="portal-surface payments-hub__records" aria-labelledby="payments-records">
        <header class="payments-hub__records-head">
          <h2 id="payments-records" class="portal-surface__title">
            {{ t('portal.payments.recordsTitle') }}
          </h2>
        </header>
        <DataListCard>
          <template #toolbar>
            <ListToolbar
              v-model:search="hub.searchInput.value"
              :search-placeholder="t('portal.payments.search')"
              :filter-state="filterState"
            >
              <template #filters="{ draft }">
                <MyPaymentFilters :filters="draft.filters" @filter="draft.setFilter" />
              </template>
            </ListToolbar>
          </template>
          <Skeleton v-if="hub.status.value === 'loading'" variant="text" :count="4" />
          <ErrorState
            v-else-if="hub.status.value === 'error'"
            :message="hub.errorMessage.value"
            @retry="hub.refresh"
          />
          <EmptyState
            v-else-if="!hub.tableRows.value.length && hub.isFiltered.value"
            icon="search"
            :title="t('portal.payments.emptyFiltered.title')"
            :message="t('portal.payments.emptyFiltered.message')"
          />
          <EmptyState
            v-else-if="!hub.tableRows.value.length"
            icon="receipt"
            :title="t('portal.payments.empty.all.title')"
            :message="t('portal.payments.empty.all.message')"
          />
          <MyPaymentsList
            v-else
            :rows="hub.tableRows.value"
            :busy="receipts.busy"
            @download="downloadReceipt"
          />
          <template #footer>
            <Pagination
              v-if="pagination.lastPage > 1"
              :page="pagination.page"
              :last-page="pagination.lastPage"
              @update:page="setPage"
            />
          </template>
        </DataListCard>
      </section>
    </div>
  </section>
</template>
