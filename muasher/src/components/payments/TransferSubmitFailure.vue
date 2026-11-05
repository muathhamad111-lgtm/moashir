<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PaymentNotice from './PaymentNotice.vue'
import { SUBMIT_FAILURES } from '@/composables/useBankTransferSubmission'
import { PAYMENT_CHANNELS } from '@/constants/payments'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  failure: { type: Object, required: true },
})

const { t } = useI18n()

const openOrderKey = computed(() =>
  props.failure.meta?.channel === PAYMENT_CHANNELS.ELECTRONIC ? 'cardOrder' : 'transferOrder',
)
const orderRoute = computed(() =>
  props.failure.meta?.paymentId
    ? { name: ROUTE_NAMES.PAYMENTS_COMPLETE, params: { id: props.failure.meta.paymentId } }
    : { name: ROUTE_NAMES.PAYMENTS_MINE },
)
</script>

<template>
  <PaymentNotice
    v-if="failure.kind === SUBMIT_FAILURES.OPEN_ORDER"
    :tone="openOrderKey === 'cardOrder' ? 'warning' : 'info'"
    :icon="openOrderKey === 'cardOrder' ? 'credit-card' : 'refresh-dots'"
    :title="t(`portal.transfer.failures.${openOrderKey}.title`)"
    :message="t(`portal.transfer.failures.${openOrderKey}.message`)"
    live
  >
    <template #actions>
      <RouterLink class="payment-notice__link" :to="orderRoute">
        {{ t(`portal.transfer.failures.${openOrderKey}.action`) }}
      </RouterLink>
    </template>
  </PaymentNotice>
  <PaymentNotice
    v-else-if="failure.kind === SUBMIT_FAILURES.BLOCKED"
    tone="danger"
    icon="lock"
    :title="t('portal.transfer.failures.blocked.title')"
    :message="failure.message"
    live
  >
    <template #actions>
      <RouterLink class="payment-notice__link" :to="{ name: ROUTE_NAMES.PORTAL_HELP }">
        {{ t('portal.transfer.failures.blocked.action') }}
      </RouterLink>
    </template>
  </PaymentNotice>
  <PaymentNotice
    v-else-if="failure.kind !== SUBMIT_FAILURES.ACCOUNT"
    tone="danger"
    icon="warning-triangle"
    :title="t('portal.transfer.failures.other.title')"
    :message="failure.message"
    live
  />
</template>
