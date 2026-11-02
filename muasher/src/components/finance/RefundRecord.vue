<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import { SCAN_STATUSES } from '@/constants/finance'
import { formatDate } from '@/utils/datetime'
import { formatFileSize } from '@/utils/payments/amountFormat'

const props = defineProps({
  refund: { type: Object, required: true },
  downloading: { type: Boolean, default: false },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

const SCAN_VARIANTS = { pending: 'warning', clean: 'success', infected: 'danger', failed: 'danger' }

const isClean = computed(() => props.refund.evidence.scanStatus === SCAN_STATUSES.CLEAN)
const refundedOn = computed(() => formatDate(`${props.refund.refundedOn}T12:00:00+03:00`, locale.value, { dateStyle: 'long', timeZone: 'Asia/Riyadh' }))
const recordedAt = computed(() => formatDate(props.refund.createdAt, locale.value, { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Riyadh' }))
</script>

<template>
  <Card :title="t('finance.refund.recordTitle')">
    <dl class="payment-facts refund-record">
      <div class="payment-facts__row">
        <dt>{{ t('finance.refund.amount') }}</dt>
        <dd><MoneyAmount :value="refund.amount" :currency="refund.currency" strong /></dd>
      </div>
      <div class="payment-facts__row">
        <dt>{{ t('finance.refund.refundedOn') }}</dt>
        <dd>{{ refundedOn }}</dd>
      </div>
      <div class="payment-facts__row">
        <dt>{{ t('finance.refund.transferReference') }}</dt>
        <dd><bdi dir="ltr">{{ refund.transferReference }}</bdi></dd>
      </div>
      <div class="payment-facts__row">
        <dt>{{ t('finance.refund.reason') }}</dt>
        <dd class="refund-record__reason">{{ refund.reason }}</dd>
      </div>
      <div class="payment-facts__row">
        <dt>{{ t('finance.refund.recordedBy') }}</dt>
        <dd>{{ refund.recordedBy.name }} <span class="payment-facts__muted">{{ recordedAt }}</span></dd>
      </div>
      <div class="payment-facts__row">
        <dt>{{ t('finance.refund.evidence.label') }}</dt>
        <dd class="refund-record__evidence">
          <i class="fa-solid fa-file-shield" aria-hidden="true"></i>
          <bdi dir="ltr">{{ refund.evidence.name }}</bdi>
          <span class="payment-facts__muted">{{ t('finance.common.sizeKb', { size: formatFileSize(refund.evidence.sizeBytes, locale) }) }}</span>
          <Badge :variant="SCAN_VARIANTS[refund.evidence.scanStatus] ?? 'muted'">{{ t(`finance.scan.${refund.evidence.scanStatus}`) }}</Badge>
        </dd>
      </div>
    </dl>
    <template #footer>
      <BaseButton variant="secondary" icon="fa-solid fa-file-arrow-down" :disabled="!isClean" :loading="downloading" @click="emit('download')">
        {{ t('finance.refund.downloadEvidence') }}
      </BaseButton>
    </template>
  </Card>
</template>
