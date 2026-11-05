<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import PaymentStatusBadge from './PaymentStatusBadge.vue'
import PricingBreakdown from './PricingBreakdown.vue'
import OrderCountdown from './OrderCountdown.vue'
import { PAYMENT_CHANNELS } from '@/constants/payments'

const props = defineProps({
  purpose: { type: String, required: true },
  payment: { type: Object, default: null },
  quote: { type: Object, default: null },
  channel: { type: String, default: null },
  countdown: { type: Object, default: null },
  showStatus: { type: Boolean, default: false },
})

const { t } = useI18n()
const pricing = computed(() => props.payment?.pricing ?? props.quote)
const pendingKey = computed(() =>
  props.channel === PAYMENT_CHANNELS.BANK_TRANSFER
    ? 'finance.checkout.referenceOnSubmit'
    : 'finance.checkout.amountPending',
)
</script>

<template>
  <Card>
    <div class="checkout-summary">
      <p class="checkout-summary__eyebrow">{{ t('finance.checkout.summaryTitle') }}</p>
      <h2 class="checkout-summary__service">{{ t(`finance.purposes.${purpose}`) }}</h2>
      <PricingBreakdown v-if="pricing" :pricing="pricing" />
      <p v-if="payment" class="checkout-summary__reference">
        {{ t('finance.checkout.reference') }}
        <bdi dir="ltr">{{ payment.reference }}</bdi>
      </p>
      <p v-else class="checkout-summary__pending">{{ t(pendingKey) }}</p>
      <p v-if="channel" class="checkout-summary__channel">
        {{ t('finance.checkout.channelLabel') }}
        <strong>{{ t(`finance.channels.${channel}`) }}</strong>
      </p>
      <PaymentStatusBadge v-if="showStatus && payment" :status="payment.status" />
      <OrderCountdown
        v-if="payment && countdown"
        :label="countdown.label.value"
        :remaining="countdown.remaining.value"
      />
    </div>
  </Card>
</template>
