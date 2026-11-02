<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDate } from '@/utils/datetime'

const props = defineProps({
  payment: { type: Object, required: true },
})

const { t, te, locale } = useI18n()

const STAMPS = ['createdAt', 'paidAt', 'failedAt', 'cancelledAt', 'refundedAt']

function dateOf(value) {
  return formatDate(value, locale.value, { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Riyadh' })
}

const method = computed(() => (props.payment.method ? t(`finance.methods.${props.payment.method}`) : ''))
const reason = computed(() => {
  const value = props.payment.failureReason
  return value && te(`finance.failureReasons.${value}`) ? t(`finance.failureReasons.${value}`) : ''
})
const stamps = computed(() => STAMPS.filter((key) => props.payment[key]).map((key) => ({ key, value: dateOf(props.payment[key]) })))
</script>

<template>
  <dl class="payment-facts">
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.reference') }}</dt>
      <dd><bdi dir="ltr">{{ payment.reference }}</bdi></dd>
    </div>
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.service') }}</dt>
      <dd>{{ t(`finance.purposes.${payment.purpose}`) }}</dd>
    </div>
    <div v-if="method" class="payment-facts__row">
      <dt>{{ t('finance.fields.method') }}</dt>
      <dd>
        {{ method }}
        <span v-if="payment.cardLast4" class="payment-facts__card">{{ t('finance.fields.cardEnding', { last4: payment.cardLast4 }) }}</span>
      </dd>
    </div>
    <div v-if="reason" class="payment-facts__row">
      <dt>{{ t('finance.fields.failureReason') }}</dt>
      <dd>{{ reason }}</dd>
    </div>
    <div v-for="stamp in stamps" :key="stamp.key" class="payment-facts__row">
      <dt>{{ t(`finance.fields.${stamp.key}`) }}</dt>
      <dd>{{ stamp.value }}</dd>
    </div>
  </dl>
</template>
