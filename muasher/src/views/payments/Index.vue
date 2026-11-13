<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import PaymentStatTiles from '@/components/payments/portal/PaymentStatTiles.vue'
import PendingOrderCard from '@/components/payments/portal/PendingOrderCard.vue'
import MyPaymentsList from '@/components/payments/portal/MyPaymentsList.vue'
import MyPaymentFilters from '@/components/payments/portal/MyPaymentFilters.vue'
import { usePaymentsHub } from '@/composables/usePaymentsHub'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { paymentsService } from '@/services/paymentsService'

const FILTERS_ID = 'my-payment-filters'

const { t } = useI18n()
const hub = usePaymentsHub()
const receipts = useSignedDownload()
const filtersOpen = ref(false)
const { pagination, setPage } = hub.paginator
const filterLabel = computed(() =>
  hub.activeFilterCount.value
    ? t('portal.payments.filters.active', { count: hub.activeFilterCount.value })
    : t('portal.payments.filters.toggle'),
)

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
          <div class="payments-hub__tools">
            <input
              v-model="hub.searchInput.value"
              class="field__control payments-hub__search"
              type="search"
              name="payments-search"
              :placeholder="t('portal.payments.search')"
              :aria-label="t('portal.payments.search')"
            />
            <button
              type="button"
              class="payments-hub__filter-toggle"
              :class="{ 'is-active': filtersOpen || hub.activeFilterCount.value }"
              :aria-expanded="filtersOpen"
              :aria-controls="FILTERS_ID"
              :aria-label="filterLabel"
              data-testid="payments-filter-toggle"
              @click="filtersOpen = !filtersOpen"
            >
              <AppIcon name="filter" />
              <span aria-hidden="true">{{ t('portal.payments.filters.toggle') }}</span>
              <span
                v-if="hub.activeFilterCount.value"
                class="payments-hub__filter-count"
                aria-hidden="true"
                >{{ hub.activeFilterCount.value }}</span
              >
            </button>
            <button
              v-if="hub.isFiltered.value"
              type="button"
              class="payments-hub__clear"
              data-testid="payments-filter-clear"
              @click="hub.clearFilters"
            >
              <AppIcon name="x" />
              {{ t('portal.payments.filters.clear') }}
            </button>
          </div>
        </header>
        <MyPaymentFilters
          v-show="filtersOpen"
          :panel-id="FILTERS_ID"
          :filters="hub.filters.filters"
          @filter="hub.setFilter"
        />
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
        <Pagination
          v-if="pagination.lastPage > 1"
          :page="pagination.page"
          :last-page="pagination.lastPage"
          @update:page="setPage"
        />
      </section>
    </div>
  </section>
</template>
