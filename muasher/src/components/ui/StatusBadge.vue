<script setup>
import { statusLabelKey } from '@/utils/canonicalStatus'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { STATUS_VARIANTS } from '@/constants/statuses'
import Badge from './Badge.vue'

const props = defineProps({
  status: {
    type: String,
    required: true,
    validator: (value) => value in STATUS_VARIANTS,
  },
})

const { t } = useI18n()
const variant = computed(() => STATUS_VARIANTS[props.status] ?? 'muted')
const label = computed(() => t(statusLabelKey(props.status)))
</script>

<template>
  <Badge :variant="variant">{{ label }}</Badge>
</template>
