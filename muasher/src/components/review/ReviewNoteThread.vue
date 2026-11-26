<script setup>
import { useI18n } from 'vue-i18n'
import { formatRiyadhDateTime } from '@/utils/datetime'

// The other staff's internal notes on one unit, read-only: who wrote it, in which role and when.
defineProps({
  notes: { type: Array, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <ul class="review-note-thread" :aria-label="t('review.internalNotes.others')">
    <li v-for="note in notes" :key="note.id" class="review-note-thread__item">
      <p class="review-note-thread__meta">
        <!-- The API hides some authors' names (a Super Admin, or staff names from an assessor). -->
        <span v-if="note.author.name" class="review-note-thread__author">{{
          note.author.name
        }}</span>
        <span>{{ t(`review.internalNotes.roles.${note.author.role}`) }}</span>
        <time :datetime="note.updatedAt">{{ formatRiyadhDateTime(note.updatedAt, locale) }}</time>
      </p>
      <p class="review-note-thread__body">{{ note.body }}</p>
    </li>
  </ul>
</template>
