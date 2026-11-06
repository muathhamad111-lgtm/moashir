<script setup>
import { useI18n } from 'vue-i18n'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import { formatNumber } from '@/utils/formatNumber'

defineProps({
  stats: { type: Object, required: true },
})

const { t, n } = useI18n()
</script>

<template>
  <dl class="payment-stats">
    <div class="payment-stats__tile">
      <dt class="payment-stats__label">{{ t('portal.payments.stats.count') }}</dt>
      <dd class="payment-stats__value">
        <bdi dir="ltr">{{ formatNumber(n, stats.count ?? 0) }}</bdi>
        <span class="payment-stats__unit">{{
          t('portal.payments.stats.unit', stats.count ?? 0)
        }}</span>
      </dd>
    </div>
    <div class="payment-stats__tile">
      <dt class="payment-stats__label">{{ t('portal.payments.stats.paid') }}</dt>
      <dd class="payment-stats__value">
        <bdi dir="ltr">{{ formatNumber(n, stats.paidCount ?? 0) }}</bdi>
        <span class="payment-stats__unit">{{
          t('portal.payments.stats.unit', stats.paidCount ?? 0)
        }}</span>
      </dd>
    </div>
    <div class="payment-stats__tile">
      <dt class="payment-stats__label">{{ t('portal.payments.stats.totalPaid') }}</dt>
      <dd class="payment-stats__value">
        <MoneyAmount :value="stats.totalPaid ?? '0.00'" :currency="stats.currency ?? 'SAR'" />
      </dd>
    </div>
    <div class="payment-stats__tile">
      <dt class="payment-stats__label">{{ t('portal.payments.stats.totalVat') }}</dt>
      <dd class="payment-stats__value">
        <MoneyAmount :value="stats.totalVat ?? '0.00'" :currency="stats.currency ?? 'SAR'" />
      </dd>
    </div>
  </dl>
</template>
