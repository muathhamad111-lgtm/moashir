<script setup>
defineProps({
  eyebrow: { type: String, required: true },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <header class="finance-header">
    <div class="finance-header__text">
      <p class="finance-header__eyebrow">{{ eyebrow }}</p>
      <h1 class="finance-header__title">{{ title }}</h1>
      <p v-if="subtitle" class="finance-header__subtitle">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="finance-header__actions"><slot name="actions" /></div>
  </header>
</template>
