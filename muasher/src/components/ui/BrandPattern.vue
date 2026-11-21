<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'stars',
    validator: (value) => ['stars', 'lattice'].includes(value),
  },
})

const classes = computed(() => ['brand-pattern', `brand-pattern--${props.variant}`])
</script>

<template>
  <span :class="classes" aria-hidden="true"></span>
</template>
