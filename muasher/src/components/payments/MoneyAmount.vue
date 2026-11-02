<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatAmount } from '@/utils/payments/amountFormat'

const props = defineProps({
  value: { type: String, default: null },
  currency: { type: String, default: 'SAR' },
  strong: { type: Boolean, default: false },
})

const { t, te, locale } = useI18n()

const amount = computed(() => formatAmount(props.value, locale.value))
const currencyLabel = computed(() => (te(`finance.currency.${props.currency}`) ? t(`finance.currency.${props.currency}`) : props.currency))
</script>

<template>
  <span class="money-amount" :class="{ 'money-amount--strong': strong }">
    <template v-if="amount">
      <bdi class="money-amount__value" dir="ltr">{{ amount }}</bdi>
      <span class="money-amount__currency">{{ currencyLabel }}</span>
    </template>
    <span v-else class="money-amount__empty">{{ t('finance.common.none') }}</span>
  </span>
</template>
