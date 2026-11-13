<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { CHANNEL_ICONS } from '@/constants/bankTransfer'
import { PAYMENT_CHANNELS } from '@/constants/payments'

const props = defineProps({
  payment: { type: Object, required: true },
  withMethod: { type: Boolean, default: true },
})

const { t } = useI18n()
const channel = computed(() => props.payment.channel ?? PAYMENT_CHANNELS.ELECTRONIC)
const method = computed(() =>
  props.withMethod && channel.value === PAYMENT_CHANNELS.ELECTRONIC && props.payment.method
    ? t(`finance.methods.${props.payment.method}`)
    : '',
)
</script>

<template>
  <span class="payment-channel">
    <AppIcon :name="CHANNEL_ICONS[channel]" />
    <span>{{ t(`finance.channels.${channel}`) }}</span>
    <span v-if="method" class="payment-channel__method">{{ method }}</span>
  </span>
</template>
