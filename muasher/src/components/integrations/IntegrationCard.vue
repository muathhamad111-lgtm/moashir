<script setup>
import IntegrationBadges from './IntegrationBadges.vue'
import LastTestLine from './LastTestLine.vue'

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  status: { type: Object, default: null },
  showLastTest: { type: Boolean, default: true },
})
</script>

<template>
  <section class="integration-card">
    <header class="integration-card__header">
      <div class="integration-card__heading">
        <h2 class="integration-card__title">{{ title }}</h2>
        <IntegrationBadges v-if="status" v-bind="status" />
      </div>
      <p v-if="description" class="integration-card__desc">{{ description }}</p>
      <LastTestLine v-if="status && showLastTest" :last-test="status.lastTest" />
    </header>
    <div class="integration-card__body">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="integration-card__footer">
      <slot name="footer" />
    </footer>
  </section>
</template>
