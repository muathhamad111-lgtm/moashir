<script setup>
import { useI18n } from 'vue-i18n'
import BrandLoaderMark from './BrandLoaderMark.vue'

// The official waiting page, in three placements:
// - `screen` covers the viewport (the boot, before any layout exists);
// - `overlay` covers a layout's content area while the next page loads, the shell staying;
// - `page` stands in the flow of a page waiting for its first data.
// Callers time it (a short delay, a minimum time); `showsMark` lets them keep the place
// reserved and announced while the mark itself waits for that delay.
defineProps({
  variant: {
    type: String,
    default: 'page',
    validator: (value) => ['screen', 'overlay', 'page'].includes(value),
  },
  showsMark: { type: Boolean, default: true },
})

const { t } = useI18n()
</script>

<template>
  <div
    class="app-loading"
    :class="`app-loading--${variant}`"
    role="status"
    aria-busy="true"
    data-testid="app-loading"
  >
    <div v-if="showsMark" class="app-loading__center">
      <BrandLoaderMark class="app-loading__mark" />
    </div>
    <span class="app-loading__label">{{ t('common.loading') }}</span>
  </div>
</template>
