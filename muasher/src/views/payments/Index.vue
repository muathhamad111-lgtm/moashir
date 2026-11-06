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
import { usePaymentsHub } from '@/composables/usePaymentsHub'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { paymentsService } from '@/services/paymentsService'
import AppIcon from '@/components/ui/AppIcon.vue'

const PANEL_ID = 'payments-panel'

const { t } = useI18n()
const hub = usePaymentsHub()
const receipts = useSignedDownload()

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
      :subtitle="t('portal.payments.subtitle')"
      :tabs="hub.tabs.value"
      :tab="hub.tab.value"
      :tabs-label="t('portal.payments.tabsLabel')"
      :panel-id="PANEL_ID"
      @update:tab="hub.tab.value = $event"
    />

    <div
      :id="PANEL_ID"
      class="portal-area__body payments-hub__body"
      role="tabpanel"
      :aria-labelledby="`${PANEL_ID}-tab-${hub.tab.value}`"
    >
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
        <Skeleton v-if="hub.status.value === 'loading'" variant="text" :count="4" />
        <ErrorState
          v-else-if="hub.status.value === 'error'"
          :message="hub.errorMessage.value"
          @retry="hub.load"
        />
        <EmptyState
          v-else-if="!hub.tableRows.value.length"
          icon="receipt"
          :title="t(`portal.payments.empty.${hub.tab.value}.title`)"
          :message="t(`portal.payments.empty.${hub.tab.value}.message`)"
        />
        <MyPaymentsList
          v-else
          :rows="hub.tableRows.value"
          :busy="receipts.busy"
          @download="downloadReceipt"
        />
        <Pagination
          v-if="hub.lastPage.value > 1"
          :page="hub.page.value"
          :last-page="hub.lastPage.value"
          @update:page="hub.setPage"
        />
      </section>

      <p v-if="hub.hasTestRows.value" class="portal-area__note portal-area__note--warning">
        <AppIcon name="flask" />
        {{ t('portal.payments.testNote') }}
      </p>
      <p class="portal-area__note">
        <AppIcon name="info-circle" />
        {{ t('portal.payments.note') }}
      </p>
    </div>
  </section>
</template>
