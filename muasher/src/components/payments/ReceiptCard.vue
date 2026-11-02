<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PricingBreakdown from './PricingBreakdown.vue'
import PaymentNotice from './PaymentNotice.vue'
import { RECEIPT_STATUSES } from '@/constants/payments'
import { formatDate, formatHijriDate } from '@/utils/datetime'

const props = defineProps({
  receipt: { type: Object, required: true },
  downloading: { type: Boolean, default: false },
  unavailable: { type: Boolean, default: false },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

const isVoided = computed(() => props.receipt.status === RECEIPT_STATUSES.VOIDED)
const snapshot = computed(() => props.receipt.snapshot)
const pricing = computed(() => ({
  priceBeforeVat: snapshot.value.priceBeforeVat,
  vatRate: snapshot.value.vatRate,
  vatAmount: snapshot.value.vatAmount,
  total: snapshot.value.total,
  currency: snapshot.value.currency,
}))
const entityName = computed(() => snapshot.value.entityName?.[locale.value] || snapshot.value.entityName?.ar || '')

function gregorian(value) {
  return formatDate(value, locale.value, { dateStyle: 'long', timeZone: 'Asia/Riyadh' })
}

function hijri(value) {
  return formatHijriDate(value, locale.value)
}
</script>

<template>
  <Card>
    <article class="receipt-card" :class="{ 'receipt-card--voided': isVoided, 'receipt-card--test': receipt.isTest }">
      <header class="receipt-card__header">
        <div>
          <p class="receipt-card__eyebrow">{{ t('finance.receipt.title') }}</p>
          <h2 class="receipt-card__number">
            <bdi v-if="receipt.number" dir="ltr">{{ receipt.number }}</bdi>
            <span v-else>{{ t('finance.receipt.testNumber') }}</span>
          </h2>
        </div>
        <div class="receipt-card__badges">
          <Badge v-if="receipt.isTest" variant="warning">{{ t('finance.receipt.testBadge') }}</Badge>
          <Badge :variant="isVoided ? 'danger' : 'success'">{{ t(`finance.receipt.status.${receipt.status}`) }}</Badge>
        </div>
      </header>

      <p v-if="receipt.isTest" class="receipt-card__watermark" role="note">{{ t('finance.receipt.testNote') }}</p>
      <p v-if="isVoided" class="receipt-card__watermark receipt-card__watermark--void" role="note">{{ t('finance.receipt.voidNote') }}</p>

      <dl class="receipt-card__facts">
        <div class="receipt-card__fact">
          <dt>{{ t('finance.receipt.issuedAt') }}</dt>
          <dd>{{ gregorian(receipt.issuedAt) }} <span class="receipt-card__hijri">{{ hijri(receipt.issuedAt) }}</span></dd>
        </div>
        <div v-if="receipt.voidedAt" class="receipt-card__fact">
          <dt>{{ t('finance.receipt.voidedAt') }}</dt>
          <dd>{{ gregorian(receipt.voidedAt) }} <span class="receipt-card__hijri">{{ hijri(receipt.voidedAt) }}</span></dd>
        </div>
        <div class="receipt-card__fact">
          <dt>{{ t('finance.fields.entity') }}</dt>
          <dd>{{ entityName }} <bdi class="receipt-card__muted" dir="ltr">{{ snapshot.entityReference }}</bdi></dd>
        </div>
        <div class="receipt-card__fact">
          <dt>{{ t('finance.fields.reference') }}</dt>
          <dd><bdi dir="ltr">{{ snapshot.paymentReference }}</bdi></dd>
        </div>
        <div class="receipt-card__fact">
          <dt>{{ t('finance.fields.service') }}</dt>
          <dd>{{ t(`finance.purposes.${snapshot.purpose}`) }}</dd>
        </div>
        <div v-if="receipt.reissueCount" class="receipt-card__fact">
          <dt>{{ t('finance.receipt.reissued') }}</dt>
          <dd>{{ receipt.reissueCount }}</dd>
        </div>
      </dl>

      <PricingBreakdown :pricing="pricing" />

      <PaymentNotice
        v-if="unavailable"
        tone="warning"
        icon="fa-solid fa-file-circle-exclamation"
        :title="t('finance.receipt.unavailableTitle')"
        :message="t('finance.receipt.unavailableMessage')"
        live
      />

      <footer class="receipt-card__actions">
        <BaseButton variant="secondary" icon="fa-solid fa-file-arrow-down" :loading="downloading" @click="emit('download')">
          {{ t('finance.receipt.download') }}
        </BaseButton>
        <slot name="actions" />
      </footer>
    </article>
  </Card>
</template>
