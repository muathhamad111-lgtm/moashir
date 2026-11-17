<script setup>
import { useId } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: '' },
  note: { type: String, default: '' },
  wide: { type: Boolean, default: false },
})

const headingId = useId()
</script>

<template>
  <section
    class="overview-panel"
    :class="{ 'overview-panel--wide': wide }"
    :aria-labelledby="headingId"
  >
    <header class="overview-panel__head">
      <span v-if="icon" class="overview-panel__icon" aria-hidden="true">
        <AppIcon :name="icon" />
      </span>
      <h3 :id="headingId" class="overview-panel__title">{{ title }}</h3>
      <p v-if="note" class="overview-panel__note">{{ note }}</p>
    </header>
    <div class="overview-panel__body">
      <slot />
    </div>
  </section>
</template>
