<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

defineProps({
  payment: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <span class="request-payment-summary">
    <span class="request-payment-summary__line">
      <PaymentStatusBadge :status="payment.status" />
      <RouterLink
        v-if="payment.id"
        class="request-queue__link"
        :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: payment.id } }"
        ><bdi dir="ltr">{{ payment.reference }}</bdi></RouterLink
      >
      <bdi v-else dir="ltr">{{ payment.reference }}</bdi>
    </span>
    <bdi v-if="payment.receipt?.number" dir="ltr" class="request-queue__muted">{{
      payment.receipt.number
    }}</bdi>
    <span v-else-if="payment.receipt?.isTest" class="request-queue__muted">{{
      t('requests.detail.paymentCard.testReceipt')
    }}</span>
  </span>
</template>
