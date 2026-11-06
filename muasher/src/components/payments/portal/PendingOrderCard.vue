<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import { useCountdown } from '@/composables/useCountdown'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatDate } from '@/utils/datetime'
import { formatAmount } from '@/utils/payments/amountFormat'
import { paymentYearOf } from '@/utils/payments/myPaymentRow'
import AppIcon from '@/components/ui/AppIcon.vue'

const MINUTE = 60

const props = defineProps({
  order: { type: Object, required: true },
})

const emit = defineEmits(['expired'])
const { t, locale } = useI18n()

const deadline = computed(() => props.order.expiresAt)
const countdown = useCountdown(deadline)
const minutesLeft = computed(() => Math.max(1, Math.ceil(countdown.remaining.value / MINUTE)))
const service = computed(() => t(`finance.purposes.${props.order.purpose}`))

function clock(value) {
  return formatDate(value, locale.value, {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'Asia/Riyadh',
  })
}

const created = computed(() => clock(props.order.createdAt))
const expires = computed(() => clock(props.order.expiresAt))
const breakdown = computed(() =>
  t('portal.payments.pending.breakdown', {
    before: formatAmount(props.order.pricing.priceBeforeVat, locale.value),
    vat: formatAmount(props.order.pricing.vatAmount, locale.value),
  }),
)

watch(countdown.expired, (expired) => {
  if (expired) emit('expired', props.order.id)
})
</script>

<template>
  <article class="pending-order" :aria-labelledby="`pending-${order.id}`">
    <span class="pending-order__icon" aria-hidden="true">
      <AppIcon name="credit-card" />
    </span>
    <div class="pending-order__main">
      <p class="pending-order__badge">
        <span class="pending-order__dot" aria-hidden="true"></span>
        {{ t('portal.payments.pending.badge', { minutes: minutesLeft }, minutesLeft) }}
      </p>
      <h2 :id="`pending-${order.id}`" class="pending-order__title">
        {{ t('portal.payments.pending.title', { service }) }}
        <bdi dir="ltr">{{ paymentYearOf(order) }}</bdi>
      </h2>
      <p class="pending-order__meta">
        {{ t('portal.payments.pending.meta', { time: created }) }}
        <bdi class="pending-order__reference" dir="ltr">{{ order.reference }}</bdi>
      </p>
      <p v-if="order.purpose === 'accredited'" class="pending-order__hint">
        {{ t('portal.payments.pending.opensForm') }}
      </p>
    </div>
    <div class="pending-order__amount">
      <span class="pending-order__label">{{ t('portal.payments.totalWithVat') }}</span>
      <MoneyAmount :value="order.pricing.total" :currency="order.pricing.currency" strong />
      <span class="pending-order__breakdown">{{ breakdown }}</span>
    </div>
    <div class="pending-order__cta">
      <router-link
        class="btn btn--primary"
        :to="{ name: ROUTE_NAMES.PAYMENTS_COMPLETE, params: { id: order.id } }"
      >
        {{ t('portal.payments.complete') }}
        <AppIcon name="arrow-right" />
      </router-link>
      <p class="pending-order__expires">
        {{ t('portal.payments.pending.expires', { time: expires }) }}
      </p>
    </div>
  </article>
</template>
