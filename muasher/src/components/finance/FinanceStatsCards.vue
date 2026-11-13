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
    label: t('finance.stats.totalIncome'),
  },
  {
    id: 'vat',
    icon: 'percent',
    value: money(props.stats?.totalVat),
    label: t('finance.stats.totalVat'),
  },
  {
    id: 'refunded',
    icon: 'undo',
    value: money(props.stats?.totalRefunded),
    label: t('finance.stats.totalRefunded'),
  },
  {
    id: 'count',
    icon: 'menu',
    value: count(props.stats?.transactionsCount),
    label: t('finance.stats.transactions'),
  },
  {
    id: 'completed',
    icon: 'check-circle',
    value: count(props.stats?.completedCount),
    label: t('finance.stats.completed'),
  },
  {
    id: 'incomplete',
    icon: 'refresh-dots',
    value: count(props.stats?.incompleteCount),
    label: t('finance.stats.incomplete'),
  },
  {
    id: 'refundedCount',
    icon: 'undo',
    value: count(props.stats?.refundedCount),
    label: t('finance.stats.refundedCount'),
  },
])
</script>

<template>
  <StatsCards :stats="cards" :loading="loading" :skeleton-count="7" />
</template>
