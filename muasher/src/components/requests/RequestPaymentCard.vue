<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import RecordCardHeader from '@/components/records/RecordCardHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import PaymentChannelCell from '@/components/payments/portal/PaymentChannelCell.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

defineProps({
  payment: { type: Object, required: true },
  downloading: { type: Boolean, default: false },
})

const emit = defineEmits(['download'])
const { t } = useI18n()
</script>

<template>
  <Card data-testid="request-payment-card">
    <template #header>
      <RecordCardHeader
        :title="t('requests.detail.paymentCard.title')"
        :actions-label="t('requests.detail.paymentCard.actions')"
      >
        <template v-if="payment.id" #actions>
          <BaseButton
            v-if="payment.receipt?.id"
            variant="primary"
            size="sm"
            icon="download"
            :loading="downloading"
            @click="emit('download', payment.receipt.id)"
            >{{ t('requests.detail.paymentCard.downloadReceipt') }}</BaseButton
          >
          <BaseButton
            variant="secondary"
            size="sm"
            icon="sar"
            :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: payment.id } }"
            >{{ t('requests.detail.paymentCard.openRecord') }}</BaseButton
          >
        </template>
      </RecordCardHeader>
    </template>
    <dl class="request-payment-card__facts">
      <div class="request-payment-card__row">
        <dt>{{ t('requests.detail.paymentCard.status') }}</dt>
        <dd><PaymentStatusBadge :status="payment.status" /></dd>
      </div>
      <div class="request-payment-card__row">
        <dt>{{ t('requests.detail.paymentCard.reference') }}</dt>
        <dd>
          <RouterLink
            v-if="payment.id"
            class="request-queue__link"
            :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: payment.id } }"
            ><bdi dir="ltr">{{ payment.reference }}</bdi></RouterLink
          >
          <bdi v-else dir="ltr">{{ payment.reference }}</bdi>
        </dd>
      </div>
      <div class="request-payment-card__row">
        <dt>{{ t('requests.detail.paymentCard.receipt') }}</dt>
        <dd>
          <bdi v-if="payment.receipt?.number" dir="ltr">{{ payment.receipt.number }}</bdi>
          <span v-else-if="payment.receipt">{{ t('finance.receipt.noNumber') }}</span>
          <span v-else class="request-queue__muted">{{
            t('requests.detail.paymentCard.noReceipt')
          }}</span>
        </dd>
      </div>
      <div class="request-payment-card__row">
        <dt>{{ t('requests.detail.paymentCard.channel') }}</dt>
        <dd><PaymentChannelCell :payment="payment" :with-method="false" /></dd>
      </div>
      <div class="request-payment-card__row">
        <dt>{{ t('requests.detail.paymentCard.paidAt') }}</dt>
        <dd><DateStamp :value="payment.paidAt" /></dd>
      </div>
    </dl>
  </Card>
</template>
