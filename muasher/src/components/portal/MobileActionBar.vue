<script setup>
defineProps({
  label: { type: String, required: true },
})
</script>

<template>
  <div class="mobile-action-bar" role="region" :aria-label="label">
    <div class="mobile-action-bar__inner">
      <slot />
    </div>
  </div>
</template>
