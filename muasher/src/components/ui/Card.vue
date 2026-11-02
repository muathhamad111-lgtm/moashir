<script setup>
defineProps({
  title: { type: String, default: '' },
})
</script>

<template>
  <section class="card">
    <header v-if="title || $slots.header" class="card__header">
      <slot name="header">{{ title }}</slot>
    </header>
    <div class="card__body">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="card__footer">
      <slot name="footer" />
    </footer>
  </section>
</template>
