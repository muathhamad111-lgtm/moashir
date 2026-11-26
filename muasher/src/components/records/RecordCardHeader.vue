<script setup>
// A record tab card's header (operator note 2026-11-26, v4.1): the title at the start and the card's own actions at
// the end, primary first and a destructive one last. On a narrow card the actions wrap under the title.
defineProps({
  title: { type: String, required: true },
  actionsLabel: { type: String, default: '' },
})
</script>

<template>
  <div class="record-card-header">
    <h2 class="card__title-text">{{ title }}</h2>
    <div
      v-if="$slots.actions"
      class="record-card-header__actions"
      role="group"
      :aria-label="actionsLabel || undefined"
    >
      <slot name="actions" />
    </div>
  </div>
</template>
