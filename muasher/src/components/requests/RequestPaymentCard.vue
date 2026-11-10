<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
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
  <Card :title="t('requests.detail.paymentCard.title')" data-testid="request-payment-card">
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
          <span v-else-if="payment.receipt?.isTest">{{ t('finance.receipt.notProof') }}</span>
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
    <template v-if="payment.id" #footer>
      <div class="request-payment-card__actions">
        <RouterLink
          class="request-payment-card__record"
          :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTION, params: { id: payment.id } }"
        >
          <AppIcon name="sar" />
          {{ t('requests.detail.paymentCard.openRecord') }}
        </RouterLink>
        <BaseButton
          v-if="payment.receipt?.id"
          variant="ghost"
          icon="download"
          :loading="downloading"
          @click="emit('download', payment.receipt.id)"
        >
          {{ t('requests.detail.paymentCard.downloadReceipt') }}
        </BaseButton>
      </div>
    </template>
  </Card>
</template>
