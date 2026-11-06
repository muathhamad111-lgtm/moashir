<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { ibanLast4 } from '@/utils/payments/iban'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  transaction: { type: Object, required: true },
})

const STAMPS = [
  'createdAt',
  'expiresAt',
  'verifiedAt',
  'paidAt',
  'failedAt',
  'cancelledAt',
  'rejectedAt',
  'latePaidAt',
  'manuallySettledAt',
  'refundedAt',
]

const { t, te, locale } = useI18n()

function dateOf(value) {
  return formatDate(value, locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Riyadh',
  })
}

const entityName = computed(
  () => props.transaction.entity.name?.[locale.value] || props.transaction.entity.name?.ar || '',
)
const transferAccount = computed(() => {
  const account = props.transaction.bankTransfer?.bankAccount
  return account
    ? t('finance.fields.destinationLine', {
        bank: localizedText(account.bankName, locale.value),
        last4: ibanLast4(account.iban),
      })
    : ''
})
const reason = computed(() => {
  const value = props.transaction.failureReason
  return value && te(`finance.failureReasons.${value}`) ? t(`finance.failureReasons.${value}`) : ''
})
const stamps = computed(() =>
  STAMPS.filter((key) => props.transaction[key]).map((key) => ({
    key,
    value: dateOf(props.transaction[key]),
  })),
)
</script>

<template>
  <dl class="payment-facts">
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.entity') }}</dt>
      <dd>
        {{ entityName }}
        <bdi class="payment-facts__muted" dir="ltr">{{ transaction.entity.reference }}</bdi>
        <bdi class="payment-facts__muted" dir="ltr">{{ transaction.entity.email }}</bdi>
      </dd>
    </div>
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.service') }}</dt>
      <dd>{{ t(`finance.purposes.${transaction.purpose}`) }}</dd>
    </div>
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.channel') }}</dt>
      <dd>{{ t(`finance.channels.${transaction.channel ?? 'electronic'}`) }}</dd>
    </div>
    <div v-if="transferAccount" class="payment-facts__row">
      <dt>{{ t('finance.fields.destination') }}</dt>
      <dd>
        {{ transferAccount }}
        <RouterLink
          class="payment-facts__link"
          :to="{ name: ROUTE_NAMES.FINANCE_TRANSFER, params: { id: transaction.id } }"
        >
          {{ t('finance.transfers.openReview') }}
        </RouterLink>
      </dd>
    </div>
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.method') }}</dt>
      <dd>
        {{
          transaction.method ? t(`finance.methods.${transaction.method}`) : t('finance.common.none')
        }}
        <span v-if="transaction.cardLast4" class="payment-facts__card">{{
          t('finance.fields.cardEnding', { last4: transaction.cardLast4 })
        }}</span>
      </dd>
    </div>
    <div class="payment-facts__row">
      <dt>{{ t('finance.fields.providerPaymentId') }}</dt>
      <dd>
        <bdi dir="ltr">{{ transaction.providerPaymentId ?? t('finance.common.none') }}</bdi>
      </dd>
    </div>
    <div v-if="reason" class="payment-facts__row">
      <dt>{{ t('finance.fields.failureReason') }}</dt>
      <dd>
        {{ reason }}
        <bdi v-if="transaction.failureCode" class="payment-facts__muted" dir="ltr">{{
          transaction.failureCode
        }}</bdi>
      </dd>
    </div>
    <div v-for="stamp in stamps" :key="stamp.key" class="payment-facts__row">
      <dt>{{ t(`finance.fields.${stamp.key}`) }}</dt>
      <dd>{{ stamp.value }}</dd>
    </div>
  </dl>
</template>
