<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { COUNTDOWN_WARNING_SECONDS } from '@/constants/payments'

const props = defineProps({
  label: { type: String, required: true },
  remaining: { type: Number, required: true },
})

const { t } = useI18n()
const urgent = computed(() => props.remaining <= COUNTDOWN_WARNING_SECONDS)
const minutesLeft = computed(() => Math.ceil(props.remaining / 60))
</script>

<template>
  <p class="order-countdown" :class="{ 'order-countdown--urgent': urgent }">
    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
    <span>{{ t('finance.checkout.expiresIn') }}</span>
    <time class="order-countdown__time" dir="ltr" aria-hidden="true">{{ label }}</time>
    <span class="order-countdown__live" aria-live="polite">{{ t('finance.checkout.minutesLeft', { minutes: minutesLeft }) }}</span>
  </p>
</template>
