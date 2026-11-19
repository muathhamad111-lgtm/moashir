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
  <span v-else-if="newRequestRouteOf(payment)" class="payment-action__icons">
    <router-link
      class="payment-action payment-action--icon"
      :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id: payment.id } }"
      :title="t('portal.payments.viewRejection')"
      :aria-label="`${t('portal.payments.viewRejection')} ${payment.reference}`"
      data-action="rejection"
    >
      <AppIcon name="eye" />
    </router-link>
    <router-link
      class="payment-action payment-action--icon"
      :to="newRequestRouteOf(payment)"
      :title="t('portal.transfer.newRequest')"
      :aria-label="`${t('portal.transfer.newRequest')} ${payment.reference}`"
      data-action="new-request"
    >
      <AppIcon name="refresh" />
    </router-link>
  </span>
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
