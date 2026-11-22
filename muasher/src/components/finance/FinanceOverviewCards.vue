<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import OverviewSplitCard from './OverviewSplitCard.vue'
import { PAYMENT_CHANNELS } from '@/constants/payments'
import { channelSummary, overallSplit } from '@/utils/finance/overviewStats'
import { formatAmount } from '@/utils/payments/amountFormat'
import { withLatinNumerals } from '@/utils/numerals'

// Revenue, the operations by status and one card per payment channel. A status cell filters the list.
const props = defineProps({
  stats: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])
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

const channels = computed(() =>
  [PAYMENT_CHANNELS.ELECTRONIC, PAYMENT_CHANNELS.BANK_TRANSFER].map((channel) =>
    channelSummary(props.stats, channel),
  ),
)
const revenueDetail = computed(() =>
  t('finance.page.cards.revenueDetail', {
    vat: money(props.stats?.totalVat),
    refunded: money(props.stats?.totalRefunded),
  }),
)
</script>

<template>
  <div class="finance-overview" :aria-busy="loading || undefined">
    <div class="finance-overview__row">
      <section class="finance-overview__card finance-overview__revenue">
        <span class="finance-overview__icon" aria-hidden="true"><AppIcon name="sar" /></span>
        <div class="finance-overview__main">
          <Skeleton v-if="loading" variant="text" />
          <strong v-else class="finance-overview__value">{{ money(stats?.totalIncome) }}</strong>
          <h2 class="finance-overview__label">{{ t('finance.page.cards.revenue') }}</h2>
          <p v-if="!loading" class="finance-overview__detail">{{ revenueDetail }}</p>
        </div>
      </section>
      <OverviewSplitCard
        icon="menu"
        :label="t('finance.page.cards.operations')"
        :total="count(stats?.transactionsCount)"
        :cells="overallSplit(stats)"
        :loading="loading"
        :format-count="count"
        @select="emit('select', { status: $event })"
      />
    </div>
    <div class="finance-overview__row finance-overview__row--channels">
      <OverviewSplitCard
        v-for="channel in channels"
        :key="channel.channel"
        :icon="channel.channel === PAYMENT_CHANNELS.ELECTRONIC ? 'credit-card' : 'landmark'"
        :label="t(`finance.page.cards.channels.${channel.channel}`)"
        :total="count(channel.count)"
        :cells="channel.cells"
        :paid-total="money(channel.paidTotal)"
        :loading="loading"
        :format-count="count"
        @select="emit('select', { status: $event, type: channel.channel })"
      />
    </div>
  </div>
</template>
