<script setup>
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { canCompletePayment, canDownloadReceipt } from '@/utils/payments/myPaymentRow'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  payment: { type: Object, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['download'])
const { t } = useI18n()
</script>

<template>
  <button
    v-if="canDownloadReceipt(payment)"
    type="button"
    class="payment-action"
    :disabled="busy"
    :aria-busy="busy || undefined"
    @click="emit('download', payment)"
  >
    <AppIcon :name="busy ? 'spinner' : 'download'" />
    {{ t('portal.payments.downloadReceipt') }}
    <span class="payment-action__context">{{ payment.reference }}</span>
  </button>
  <router-link
    v-else-if="canCompletePayment(payment)"
    class="payment-action"
    :to="{ name: ROUTE_NAMES.PAYMENTS_COMPLETE, params: { id: payment.id } }"
  >
    {{ t('portal.payments.complete') }}
    <span class="payment-action__context">{{ payment.reference }}</span>
  </router-link>
</template>
