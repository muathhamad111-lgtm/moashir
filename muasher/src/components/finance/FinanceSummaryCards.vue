<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import StatusDistributionBar from './StatusDistributionBar.vue'
import { PAYMENT_CHANNELS } from '@/constants/payments'
import { channelFigures, statusDistribution } from '@/utils/finance/overviewStats'
import { formatAmount } from '@/utils/payments/amountFormat'
import { withLatinNumerals } from '@/utils/numerals'

// Three equal cards: the revenue, then each payment channel with its paid amount, its operations
// and how they split by status. Filtering lives with the list below.
const props = defineProps({
  stats: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const CHANNEL_ICONS = {
  [PAYMENT_CHANNELS.ELECTRONIC]: 'credit-card',
  [PAYMENT_CHANNELS.BANK_TRANSFER]: 'landmark',
}

function money(value) {
  return t('finance.money', {
    amount: formatAmount(value ?? '0.00', locale.value),
    currency: t('finance.currency.SAR'),
  })
}

function count(value) {
  return new Intl.NumberFormat(withLatinNumerals(locale.value)).format(value ?? 0)
}

const channels = computed(() =>
  Object.keys(CHANNEL_ICONS).map((channel) => ({
    channel,
    ...channelFigures(props.stats, channel),
    parts: statusDistribution(props.stats, channel),
  })),
)
const revenueDetail = computed(() =>
  t('finance.page.cards.revenueDetail', {
    vat: money(props.stats?.totalVat),
    refunded: money(props.stats?.totalRefunded),
  }),
)
</script>

<template>
  <div class="finance-summary" :aria-busy="loading || undefined">
    <section class="finance-summary__card" data-card="revenue">
      <h2 class="finance-summary__label">
        <span class="finance-summary__icon" aria-hidden="true"><AppIcon name="sar" /></span>
        {{ t('finance.page.cards.revenue') }}
      </h2>
      <Skeleton v-if="loading" variant="text" />
      <strong v-else class="finance-summary__value">{{ money(stats?.totalIncome) }}</strong>
      <p v-if="!loading" class="finance-summary__detail">{{ revenueDetail }}</p>
    </section>
    <section
      v-for="item in channels"
      :key="item.channel"
      class="finance-summary__card"
      :data-card="item.channel"
    >
      <h2 class="finance-summary__label">
        <span class="finance-summary__icon" aria-hidden="true">
          <AppIcon :name="CHANNEL_ICONS[item.channel]" />
        </span>
        {{ t(`finance.page.cards.channels.${item.channel}`) }}
      </h2>
      <Skeleton v-if="loading" variant="text" />
      <template v-else>
        <strong class="finance-summary__value">{{ money(item.paidTotal) }}</strong>
        <p class="finance-summary__detail">
          {{ t('finance.page.cards.paidOf', { count: count(item.count) }, item.count) }}
        </p>
        <StatusDistributionBar
          class="finance-summary__bar"
          :parts="item.parts"
          :label="
            t('finance.page.cards.distribution', {
              channel: t(`finance.page.cards.channels.${item.channel}`),
            })
          "
          :format-count="count"
        />
      </template>
    </section>
  </div>
</template>
