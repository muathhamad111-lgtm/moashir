<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import PricingBreakdown from './PricingBreakdown.vue'
import OrderCountdown from './OrderCountdown.vue'

defineProps({
  purpose: { type: String, required: true },
  payment: { type: Object, default: null },
  countdown: { type: Object, default: null },
})

const { t } = useI18n()
</script>

<template>
  <Card>
    <div class="checkout-summary">
      <p class="checkout-summary__eyebrow">{{ t('finance.checkout.summaryTitle') }}</p>
      <h2 class="checkout-summary__service">{{ t(`finance.purposes.${purpose}`) }}</h2>
      <template v-if="payment">
        <p class="checkout-summary__reference">
          {{ t('finance.checkout.reference') }}
          <bdi dir="ltr">{{ payment.reference }}</bdi>
        </p>
        <PricingBreakdown :pricing="payment.pricing" />
        <OrderCountdown
          v-if="countdown"
          :label="countdown.label.value"
          :remaining="countdown.remaining.value"
        />
      </template>
      <p v-else class="checkout-summary__pending">{{ t('finance.checkout.amountPending') }}</p>
    </div>
  </Card>
</template>
