<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatsCards from '@/components/data-list/StatsCards.vue'
import { formatAmount } from '@/utils/payments/amountFormat'
import { withLatinNumerals } from '@/utils/numerals'

const props = defineProps({
  stats: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const { t, locale } = useI18n()

function money(value) {
  return t('finance.money', {
    amount: formatAmount(value ?? '0.00', locale.value),
    currency: t('finance.currency.SAR'),
  })
}

function count(value) {
  return new Intl.NumberFormat(withLatinNumerals(locale.value)).format(value ?? 0)
}

const cards = computed(() => [
  {
    id: 'income',
    icon: 'sar',
    value: money(props.stats?.totalIncome),
    label: t('finance.operations.cards.income'),
  },
  {
    id: 'refunded',
    icon: 'undo',
    value: money(props.stats?.totalRefunded),
    label: t('finance.operations.cards.refunded'),
  },
  {
    id: 'operations',
    icon: 'menu',
    value: count(props.stats?.transactionsCount),
    label: t('finance.operations.cards.operations'),
    detail: t('finance.operations.cards.operationsValue', {
      value: money(props.stats?.transactionsValue),
    }),
  },
  {
    id: 'vat',
    icon: 'percent',
    value: money(props.stats?.totalVat),
    label: t('finance.operations.cards.vat'),
  },
])
</script>

<template>
  <StatsCards :stats="cards" :loading="loading" :skeleton-count="4" />
</template>
