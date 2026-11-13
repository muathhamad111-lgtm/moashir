<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { canDownloadReceipt } from '@/utils/payments/myPaymentRow'

defineProps({
  payment: { type: Object, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['download'])
const { t } = useI18n()
</script>

<template>
  <div class="receipt-cell">
    <bdi v-if="payment.receipt?.number" class="receipt-cell__number" dir="ltr">{{
      payment.receipt.number
    }}</bdi>
    <Badge v-else-if="payment.receipt" variant="warning">{{ t('finance.receipt.notProof') }}</Badge>
    <span v-else class="receipt-cell__none">{{ t('finance.common.none') }}</span>

    <span v-if="canDownloadReceipt(payment)" class="receipt-cell__actions">
      <router-link
        class="receipt-cell__action"
        :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id: payment.id } }"
        :title="t('portal.payments.viewReceipt')"
        :aria-label="`${t('portal.payments.viewReceipt')} ${payment.reference}`"
        data-action="view"
      >
        <AppIcon name="eye" />
        {{ t('portal.payments.receiptActions.view') }}
      </router-link>
      <button
        type="button"
        class="receipt-cell__action"
        :title="t('portal.payments.downloadReceipt')"
        :disabled="busy"
        :aria-busy="busy || undefined"
        :aria-label="`${t('portal.payments.downloadReceipt')} ${payment.reference}`"
        data-action="download"
        @click="emit('download', payment)"
      >
        <AppIcon :name="busy ? 'spinner' : 'download'" />
        {{ t('portal.payments.receiptActions.download') }}
      </button>
    </span>
  </div>
</template>
