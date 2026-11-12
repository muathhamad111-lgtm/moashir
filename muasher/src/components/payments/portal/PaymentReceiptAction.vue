<script setup>
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import {
  canCompletePayment,
  newRequestRouteOf,
  showsTransferState,
} from '@/utils/payments/myPaymentRow'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  payment: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <router-link
    v-if="canCompletePayment(payment)"
    class="payment-action"
    :to="{ name: ROUTE_NAMES.PAYMENTS_COMPLETE, params: { id: payment.id } }"
  >
    {{ t('portal.payments.complete') }}
    <span class="payment-action__context">{{ payment.reference }}</span>
  </router-link>
  <router-link
    v-else-if="newRequestRouteOf(payment)"
    class="payment-action"
    :to="newRequestRouteOf(payment)"
  >
    <AppIcon name="plus" />
    {{ t('portal.transfer.newRequest') }}
    <span class="payment-action__context">{{ payment.reference }}</span>
  </router-link>
  <router-link
    v-else-if="showsTransferState(payment)"
    class="payment-action"
    :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id: payment.id } }"
  >
    <AppIcon name="eye" />
    {{ t('portal.payments.viewDetails') }}
    <span class="payment-action__context">{{ payment.reference }}</span>
  </router-link>
</template>
