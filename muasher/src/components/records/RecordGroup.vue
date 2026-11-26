<script setup>
// A titled group of facts in the summary card.
defineProps({
  title: { type: String, required: true },
})
</script>

<template>
  <section class="record-group">
    <h2 class="record-group__title">{{ title }}</h2>
    <slot />
  </section>
</template>
