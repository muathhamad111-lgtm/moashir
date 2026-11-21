<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

// Read-only view of the assessor summary for followers and administrators; the assessor writes
// it in the completion dialog.
defineProps({
  summary: { type: Object, default: null },
})

const { t, n } = useI18n()
</script>

<template>
  <section
    class="review-summary"
    aria-labelledby="review-summary-title"
    data-testid="review-summary-read"
  >
    <header class="review-summary__header">
      <div>
        <h2 id="review-summary-title" class="review-summary__title">
          {{ t('review.summary.title') }}
        </h2>
        <p class="review-summary__lead">{{ t('review.summary.lead') }}</p>
      </div>
      <span v-if="summary" class="review-summary__saved" data-testid="review-summary-version">
        <AppIcon name="check" />
        {{ t('review.summary.version', { version: n(summary.version) }) }}
      </span>
    </header>
    <blockquote
      v-if="summary"
      class="review-summary__text"
      dir="auto"
      v-text="summary.body"
    ></blockquote>
    <p v-else class="review-summary__empty">{{ t('review.summary.empty') }}</p>
  </section>
</template>
