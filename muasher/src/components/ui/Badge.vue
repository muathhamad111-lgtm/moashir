<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'muted',
    validator: (value) => ['success', 'warning', 'danger', 'info', 'muted'].includes(value),
  },
})

const classes = computed(() => ['badge', `badge--${props.variant}`])
</script>

<template>
  <span :class="classes"><slot /></span>
</template>
