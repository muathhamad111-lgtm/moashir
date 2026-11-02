<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'text',
    validator: (value) => ['text', 'block', 'circle'].includes(value),
  },
  count: { type: Number, default: 1 },
})

const classes = computed(() => ['skeleton', `skeleton--${props.variant}`])
</script>

<template>
  <div class="skeleton-group" aria-hidden="true">
    <div v-for="index in count" :key="index" :class="classes"></div>
  </div>
</template>
