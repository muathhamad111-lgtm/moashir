<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  price: { type: Object, default: null },
  status: { type: String, required: true },
})

const { t } = useI18n()
</script>

<template>
  <Card>
    <div class="accredited-price">
      <h2 class="accredited-price__title">{{ title }}</h2>
      <p v-if="description" class="accredited-price__description">{{ description }}</p>
      <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
      <PricingBreakdown v-else-if="price" :pricing="price" />
      <p v-else class="accredited-price__note" role="note">
        <i class="fa-solid fa-calculator" aria-hidden="true"></i>
        <span>{{
          status === 'unset' ? t('accredited.price.unset') : t('finance.checkout.feesAtCheckout')
        }}</span>
      </p>
      <p v-if="price" class="accredited-price__vat-note">{{ t('accredited.price.note') }}</p>
      <div v-if="$slots.action" class="accredited-price__action">
        <slot name="action" />
      </div>
    </div>
  </Card>
</template>
