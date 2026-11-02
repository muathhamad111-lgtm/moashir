<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MoneyAmount from './MoneyAmount.vue'
import { formatRate } from '@/utils/payments/amountFormat'

const props = defineProps({
  pricing: { type: Object, required: true },
})

const { t, locale } = useI18n()
const rate = computed(() => formatRate(props.pricing.vatRate, locale.value))
</script>

<template>
  <dl class="pricing-breakdown">
    <div class="pricing-breakdown__row">
      <dt>{{ t('finance.pricing.beforeVat') }}</dt>
      <dd><MoneyAmount :value="pricing.priceBeforeVat" :currency="pricing.currency" /></dd>
    </div>
    <div class="pricing-breakdown__row">
      <dt>{{ t('finance.pricing.vat', { rate }) }}</dt>
      <dd><MoneyAmount :value="pricing.vatAmount" :currency="pricing.currency" /></dd>
    </div>
    <div class="pricing-breakdown__row pricing-breakdown__row--total">
      <dt>{{ t('finance.pricing.total') }}</dt>
      <dd><MoneyAmount :value="pricing.total" :currency="pricing.currency" strong /></dd>
    </div>
  </dl>
</template>
