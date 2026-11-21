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
  [RETURN_STATES.VERIFYING]: { tone: 'info', icon: 'spinner' },
  [RETURN_STATES.PAID]: { tone: 'success', icon: 'check-circle' },
  [RETURN_STATES.FAILED]: { tone: 'danger', icon: 'x' },
  [RETURN_STATES.PENDING]: { tone: 'info', icon: 'refresh-dots' },
  [RETURN_STATES.NOTIFY]: { tone: 'warning', icon: 'bell-dot' },
  [RETURN_STATES.CANCELLED]: { tone: 'warning', icon: 'ban' },
  [RETURN_STATES.LATE]: { tone: 'warning', icon: 'warning-triangle' },
  [RETURN_STATES.REFUNDED]: { tone: 'warning', icon: 'undo' },
  [RETURN_STATES.UNCONFIRMED]: { tone: 'warning', icon: 'question-circle' },
  [RETURN_STATES.ERROR]: { tone: 'danger', icon: 'warning-triangle' },
  [RETURN_STATES.NOT_FOUND]: { tone: 'danger', icon: 'search' },
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
