<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import PaymentFacts from '@/components/payments/PaymentFacts.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import ReceiptCard from '@/components/payments/ReceiptCard.vue'
import TestModeNotice from '@/components/payments/TestModeNotice.vue'
import PaymentNotice from '@/components/payments/PaymentNotice.vue'
import TransferRecordCard from '@/components/payments/TransferRecordCard.vue'
import { usePaymentDetail } from '@/composables/usePaymentDetail'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { isBankTransfer, newRequestRouteOf } from '@/utils/payments/myPaymentRow'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  id: { type: String, required: true },
})

const RECEIPT_KEY = 'receipt'
const CHECKOUT_ROUTES = {
  accredited: ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY,
  bag: ROUTE_NAMES.BAG_PAY,
}

const { t } = useI18n()
const { status, payment, receipt, errorMessage, load, requestReceiptLink } = usePaymentDetail(
  props.id,
)
const downloads = useSignedDownload()

useHead({ title: () => t('finance.detail.title') })

const isTransfer = computed(() => isBankTransfer(payment.value))
const isOpen = computed(
  () => !isTransfer.value && payment.value?.status === PAYMENT_STATUSES.CREATED,
)
const isPending = computed(() => payment.value?.status === PAYMENT_STATUSES.PENDING)
const isUnderReview = computed(() => payment.value?.status === PAYMENT_STATUSES.UNDER_REVIEW)
const newRequest = computed(() => (payment.value ? newRequestRouteOf(payment.value) : null))

onMounted(load)
</script>

<template>
  <section class="payment-detail">
    <RouterLink class="payment-detail__back" :to="{ name: ROUTE_NAMES.PAYMENTS_MINE }">
      <AppIcon name="arrow-left" />
      {{ t('finance.detail.back') }}
    </RouterLink>

    <div v-if="status === 'loading'" class="payment-detail__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'not-found'"
      icon="receipt"
      :title="t('finance.detail.notFound')"
    />

    <template v-else>
      <header class="payment-detail__header">
        <div>
          <p class="payment-detail__eyebrow">{{ t('finance.detail.eyebrow') }}</p>
          <h1 class="payment-detail__title">
            <bdi dir="ltr">{{ payment.reference }}</bdi>
          </h1>
        </div>
        <PaymentStatusBadge :status="payment.status" />
      </header>

      <TestModeNotice v-if="payment.isTest" compact />

      <PaymentNotice
        v-if="isPending"
        tone="info"
        icon="refresh-dots"
        :title="t('finance.detail.pendingTitle')"
        :message="t('finance.detail.pendingMessage')"
      />
      <PaymentNotice
        v-if="isOpen"
        tone="info"
        icon="credit-card"
        :title="t('finance.detail.openTitle')"
        :message="t('finance.detail.openMessage')"
      >
        <template #actions>
          <RouterLink
            class="payment-notice__link"
            :to="{ name: CHECKOUT_ROUTES[payment.purpose] }"
            >{{ t('finance.detail.continue') }}</RouterLink
          >
        </template>
      </PaymentNotice>

      <PaymentNotice
        v-if="isUnderReview"
        tone="info"
        icon="refresh-dots"
        :title="t('portal.transfer.status.underReview.title')"
        :message="t('portal.transfer.status.underReview.message')"
      />
      <PaymentNotice
        v-if="newRequest"
        tone="danger"
        icon="x"
        :title="t('portal.transfer.status.rejected.title')"
        :message="t('portal.transfer.status.rejected.message')"
      >
        <template #actions>
          <RouterLink class="payment-notice__link" :to="newRequest">{{
            t('portal.transfer.newRequest')
          }}</RouterLink>
        </template>
      </PaymentNotice>

      <div class="payment-detail__layout">
        <Card :title="t('finance.detail.summary')">
          <PaymentFacts :payment="payment" />
          <PricingBreakdown :pricing="payment.pricing" />
        </Card>
        <TransferRecordCard v-if="isTransfer" :payment="payment" />
        <ReceiptCard
          v-if="receipt"
          :receipt="receipt"
          :downloading="downloads.busy[RECEIPT_KEY] === true"
          :unavailable="downloads.unavailable[RECEIPT_KEY] === true"
          @download="downloads.download(RECEIPT_KEY, requestReceiptLink)"
        />
        <Card v-else-if="!isTransfer">
          <p class="payment-detail__no-receipt">{{ t('finance.detail.noReceipt') }}</p>
        </Card>
      </div>
    </template>
  </section>
</template>
