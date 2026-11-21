<script setup>
import { useI18n } from 'vue-i18n'
import { CONTACT_ENTRY_KINDS } from '@/constants/contactInbox'
import { formatRiyadhDateTime } from '@/utils/datetime'

defineProps({
  entries: { type: Array, default: () => [] },
})

const { t, locale } = useI18n()

function authorOf(entry) {
  return entry.author.name || t(`contactInbox.entries.authors.${entry.author.kind}`)
}
</script>

<template>
  <ol class="contact-entries">
    <li
      v-for="entry in entries"
      :key="entry.id"
      class="contact-entries__item"
      :class="`contact-entries__item--${entry.kind}`"
      :data-entry-kind="entry.kind"
    >
      <p class="contact-entries__head">
        <span class="contact-entries__author">{{ authorOf(entry) }}</span>
        <span class="contact-entries__kind">{{
          t(`contactInbox.entries.kinds.${entry.kind}`)
        }}</span>
        <time class="contact-entries__time" :datetime="entry.createdAt">{{
          formatRiyadhDateTime(entry.createdAt, locale)
        }}</time>
      </p>
      <p class="contact-entries__body">{{ entry.body }}</p>
      <p v-if="entry.kind === CONTACT_ENTRY_KINDS.NOTE" class="contact-entries__private">
        {{ t('contactInbox.entries.noteHint') }}
      </p>
    </li>
  </ol>
</template>
