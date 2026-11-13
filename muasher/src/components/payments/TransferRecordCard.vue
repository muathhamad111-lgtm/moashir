<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import TransferProofChip from './TransferProofChip.vue'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  payment: { type: Object, required: true },
})

const { t, te, locale } = useI18n()
const transfer = computed(() => props.payment.bankTransfer)

const OUTCOMES = {
  [PAYMENT_STATUSES.UNDER_REVIEW]: { key: 'underReview', icon: 'refresh-dots' },
  [PAYMENT_STATUSES.PAID]: { key: 'approved', icon: 'check-circle' },
  [PAYMENT_STATUSES.REFUNDED]: { key: 'approved', icon: 'check-circle' },
  [PAYMENT_STATUSES.REJECTED]: { key: 'rejected', icon: 'x' },
}

const outcome = computed(() => OUTCOMES[props.payment.status] ?? null)
const reason = computed(() => {
  const key = `portal.transfer.rejectionReasons.${transfer.value?.rejectionReason}`
  return transfer.value?.rejectionReason && te(key) ? t(key) : ''
})
const events = computed(() => {
  if (!transfer.value) return []
  const submitted = {
    key: 'submitted',
    icon: 'send',
    title: t('portal.transfer.timeline.submitted'),
    detail: t('portal.transfer.timeline.submittedDetail', {
      time: formatRiyadhDateTime(transfer.value.submittedAt, locale.value),
      bank: localizedText(transfer.value.bankAccount.bankName, locale.value),
    }),
  }
  if (!outcome.value) return [submitted]
  const decidedAt = transfer.value.reviewedAt
  return [
    submitted,
    {
      key: outcome.value.key,
      icon: outcome.value.icon,
      title: t(`portal.transfer.timeline.${outcome.value.key}`),
      detail: decidedAt
        ? t('portal.transfer.timeline.byFinance', {
            time: formatRiyadhDateTime(decidedAt, locale.value),
          })
        : t('portal.transfer.timeline.notified'),
    },
  ]
})
</script>

<template>
  <Card v-if="transfer" class="transfer-record">
    <h2 class="transfer-record__title">{{ t('portal.transfer.timeline.title') }}</h2>
    <ol class="transfer-record__events">
      <li v-for="event in events" :key="event.key" class="transfer-record__event">
        <span class="transfer-record__icon" aria-hidden="true"><AppIcon :name="event.icon" /></span>
        <span class="transfer-record__text">
          <span class="transfer-record__event-title">{{ event.title }}</span>
          <span class="transfer-record__event-detail">{{ event.detail }}</span>
        </span>
      </li>
    </ol>
    <p v-if="reason" class="transfer-record__reason">
      {{ t('portal.transfer.rejection.reason', { reason }) }}
    </p>
    <p v-if="transfer.reviewNote" class="transfer-record__note">
      {{ t('portal.transfer.rejection.note', { note: transfer.reviewNote }) }}
    </p>
    <h3 class="transfer-record__subtitle">{{ t('portal.transfer.proof.title') }}</h3>
    <TransferProofChip :payment="payment" />
    <p v-if="payment.status === PAYMENT_STATUSES.UNDER_REVIEW" class="transfer-record__hint">
      <AppIcon name="doc" />
      {{ t('portal.transfer.timeline.receiptAfterApproval') }}
    </p>
  </Card>
</template>
