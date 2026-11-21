<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { CHANNEL_ICONS } from '@/constants/bankTransfer'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  payment: { type: Object, required: true },
})

const { t, locale } = useI18n()
const expiry = computed(() => formatRiyadhDateTime(props.payment.expiresAt, locale.value))
</script>

<template>
  <p class="next-step__payment" data-testid="next-step-payment">
    <span class="next-step__payment-item">
      <AppIcon :name="CHANNEL_ICONS[payment.channel] ?? CHANNEL_ICONS.electronic" />
      {{ t(`finance.channels.${payment.channel}`) }}
    </span>
    <span v-if="payment.reference" class="next-step__payment-item">
      <bdi dir="ltr">{{ payment.reference }}</bdi>
    </span>
    <span v-if="expiry" class="next-step__payment-item" data-testid="next-step-expiry">
      <AppIcon name="clock" />
      <i18n-t keypath="portal.home.next.paymentExpires" scope="global">
        <template #time>
          <bdi>{{ expiry }}</bdi>
        </template>
      </i18n-t>
    </span>
  </p>
</template>
