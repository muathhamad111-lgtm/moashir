<script setup>
import AppIcon from '@/components/ui/AppIcon.vue'

// The fixed top of a staff record page (operator choice 2026-11-26, layout A): a row with the way back and the
// page's actions at its end, then ONE summary card — the identity with the status at its end, then the record's
// titled groups side by side. Everything else lives in the page's tabs.
defineProps({
  backTo: { type: Object, required: true },
  backLabel: { type: String, required: true },
})
</script>

<template>
  <div class="record-summary">
    <div class="record-summary__bar">
      <router-link class="record-summary__back" :to="backTo">
        <AppIcon name="arrow-left" />
        {{ backLabel }}
      </router-link>
      <div v-if="$slots.actions" class="record-summary__actions"><slot name="actions" /></div>
    </div>
    <section class="record-summary__card">
      <div class="record-summary__top">
        <slot name="identity" />
        <slot name="status" />
      </div>
      <div v-if="$slots.groups" class="record-summary__groups"><slot name="groups" /></div>
    </section>
  </div>
</template>
