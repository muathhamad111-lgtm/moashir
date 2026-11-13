<script setup>
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  title: { type: String, required: true },
  icon: { type: String, required: true },
  headingId: { type: String, required: true },
})
</script>

<template>
  <section class="glance-card" :aria-labelledby="headingId">
    <header class="glance-card__header">
      <span class="glance-card__icon" aria-hidden="true"><AppIcon :name="icon" /></span>
      <h3 :id="headingId" class="glance-card__title">{{ title }}</h3>
      <slot name="aside" />
    </header>
    <div class="glance-card__body">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="glance-card__footer">
      <slot name="footer" />
    </footer>
  </section>
</template>
