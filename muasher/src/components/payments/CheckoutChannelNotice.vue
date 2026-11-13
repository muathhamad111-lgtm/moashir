<script setup>
import { useI18n } from 'vue-i18n'
import PaymentNotice from './PaymentNotice.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

defineProps({
  kind: {
    type: String,
    required: true,
    validator: (value) => ['under-review', 'unavailable'].includes(value),
  },
  paymentId: { type: String, default: null },
})

const { t } = useI18n()
</script>

<template>
  <PaymentNotice
    v-if="kind === 'under-review'"
    tone="info"
    icon="refresh-dots"
    :title="t('portal.checkoutMethod.states.underReview.title')"
    :message="t('portal.checkoutMethod.states.underReview.message')"
  >
    <template #actions>
      <RouterLink
        class="payment-notice__link"
        :to="{ name: ROUTE_NAMES.PAYMENTS_COMPLETE, params: { id: paymentId } }"
      >
        {{ t('portal.checkoutMethod.states.underReview.action') }}
      </RouterLink>
    </template>
  </PaymentNotice>
  <PaymentNotice
    v-else
    tone="warning"
    icon="lock"
    :title="t('portal.checkoutMethod.states.unavailable.title')"
    :message="t('portal.checkoutMethod.states.unavailable.message')"
  >
    <template #actions>
      <RouterLink class="payment-notice__link" :to="{ name: ROUTE_NAMES.PORTAL_HELP }">
        {{ t('portal.checkoutMethod.states.unavailable.action') }}
      </RouterLink>
    </template>
  </PaymentNotice>
</template>
