<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { smsSegments } from '@/utils/smsSegments'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  text: { type: String, default: '' },
})

const { t, n } = useI18n()
const count = computed(() => smsSegments(props.text))
</script>

<template>
  <p class="notification-sms-counter" :class="{ 'is-long': count.segments > 1 }" aria-live="polite">
    {{
      t('settings.notifications.texts.sms.counter', {
        length: formatNumber(n, count.length),
        limit: formatNumber(n, count.limit),
        segments: formatNumber(n, count.segments),
      })
    }}
  </p>
</template>
