<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { PAYMENT_STATUS_VARIANTS } from '@/constants/payments'

const props = defineProps({
  status: { type: String, required: true },
})

const { t, te } = useI18n()
const variant = computed(() => PAYMENT_STATUS_VARIANTS[props.status] ?? 'muted')
const label = computed(() => (te(`finance.status.${props.status}`) ? t(`finance.status.${props.status}`) : props.status))
</script>

<template>
  <Badge :variant="variant">{{ label }}</Badge>
</template>
