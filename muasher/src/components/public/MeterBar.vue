<script setup>
import { computed } from 'vue'

const FULL = 100

const props = defineProps({
  value: { type: Number, required: true },
})

const width = computed(() => `${Math.max(0, Math.min(FULL, props.value))}%`)
</script>

<template>
  <span class="meter-bar" aria-hidden="true">
    <svg class="meter-bar__svg" focusable="false">
      <rect class="meter-bar__fill" :width="width" height="100%" />
    </svg>
  </span>
</template>
