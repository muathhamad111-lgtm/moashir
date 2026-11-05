<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PaymentNotice from './PaymentNotice.vue'
import { RETURN_STATES } from '@/composables/usePaymentReturn'

const props = defineProps({
  state: { type: String, required: true },
  payment: { type: Object, default: null },
  errorMessage: { type: String, default: '' },
})

const { t, te } = useI18n()

const PRESENTATION = {
  [RETURN_STATES.VERIFYING]: { tone: 'info', icon: 'fa-solid fa-spinner fa-spin' },
  [RETURN_STATES.PAID]: { tone: 'success', icon: 'fa-solid fa-circle-check' },
  [RETURN_STATES.FAILED]: { tone: 'danger', icon: 'fa-solid fa-circle-xmark' },
  [RETURN_STATES.PENDING]: { tone: 'info', icon: 'fa-solid fa-hourglass-half' },
  [RETURN_STATES.NOTIFY]: { tone: 'warning', icon: 'fa-solid fa-bell' },
  [RETURN_STATES.CANCELLED]: { tone: 'warning', icon: 'fa-solid fa-ban' },
  [RETURN_STATES.LATE]: { tone: 'warning', icon: 'fa-solid fa-triangle-exclamation' },
  [RETURN_STATES.REFUNDED]: { tone: 'warning', icon: 'fa-solid fa-rotate-left' },
  [RETURN_STATES.UNCONFIRMED]: { tone: 'warning', icon: 'fa-solid fa-circle-question' },
  [RETURN_STATES.ERROR]: { tone: 'danger', icon: 'fa-solid fa-circle-exclamation' },
  [RETURN_STATES.NOT_FOUND]: { tone: 'danger', icon: 'fa-solid fa-magnifying-glass' },
}

const presentation = computed(() => PRESENTATION[props.state])

const reasonText = computed(() => {
  const reason = props.payment?.failureReason
  return reason && te(`finance.failureReasons.${reason}`)
    ? t(`finance.failureReasons.${reason}`)
    : ''
})

const message = computed(() => {
  if (props.state === RETURN_STATES.FAILED && reasonText.value) return reasonText.value
  if (props.state === RETURN_STATES.ERROR && props.errorMessage) return props.errorMessage
  return t(`finance.return.${props.state}.message`)
})
</script>

<template>
  <PaymentNotice
    class="payment-outcome"
    :class="`payment-outcome--${state}`"
    :tone="presentation.tone"
    :icon="presentation.icon"
    :title="t(`finance.return.${state}.title`)"
    :message="message"
    live
  >
    <slot />
    <template v-if="$slots.actions" #actions><slot name="actions" /></template>
  </PaymentNotice>
</template>
