<script setup>
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'

defineProps({
  title: { type: String, required: true },
  message: { type: String, default: '' },
  icon: { type: String, default: 'fa-regular fa-folder-open' },
  branded: { type: Boolean, default: false },
  illustration: { type: String, default: null },
  illustrationAlt: { type: String, default: '' },
})
</script>

<template>
  <div class="empty-state" role="status">
    <div class="empty-state__art">
      <slot name="art">
        <IllustrationSlot
          v-if="branded"
          class="empty-state__illustration"
          :src="illustration"
          :alt="illustrationAlt || $t('illustrations.emptyState')"
          variant="compact"
        />
        <i v-else :class="icon" aria-hidden="true"></i>
      </slot>
    </div>
    <h3 class="empty-state__title">{{ title }}</h3>
    <p v-if="message" class="empty-state__message">{{ message }}</p>
    <div v-if="$slots.action" class="empty-state__action">
      <slot name="action" />
    </div>
  </div>
</template>
