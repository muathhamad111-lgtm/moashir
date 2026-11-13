<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, default: '' },
  testId: { type: String, default: '' },
})
</script>

<template>
  <section class="path-header-card" :data-testid="testId || undefined">
    <p v-if="eyebrow" class="path-header-card__eyebrow">{{ eyebrow }}</p>
    <p v-if="title" class="path-header-card__title">{{ title }}</p>
    <div class="path-header-card__body">
      <slot />
    </div>
    <div v-if="$slots.actions" class="path-header-card__actions">
      <slot name="actions" />
    </div>
  </section>
</template>
