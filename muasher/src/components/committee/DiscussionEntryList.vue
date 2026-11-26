<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { ENTRY_KINDS } from '@/constants/committee'

defineProps({
  entries: { type: Array, required: true },
  anonymous: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <div class="discussion-entries">
    <p class="discussion-entries__title">
      {{ t('award.discussion.entriesTitle', { count: entries.length }, entries.length) }}
    </p>
    <ul v-if="entries.length" class="discussion-entries__list">
      <li v-for="entry in entries" :key="entry.id" class="discussion-entries__item">
        <div class="discussion-entries__head">
          <span class="discussion-entries__author">{{
            anonymous
              ? entry.isMine
                ? t('award.discussion.mine')
                : t('award.discussion.member')
              : entry.member?.name
          }}</span>
          <Badge :variant="entry.kind === ENTRY_KINDS.REQUEST_EDIT ? 'warning' : 'info'">{{
            t(`award.discussion.kinds.${entry.kind}`)
          }}</Badge>
          <DateStamp :value="entry.at" />
        </div>
        <p v-if="entry.body" class="discussion-entries__body">{{ entry.body }}</p>
      </li>
    </ul>
  </div>
</template>
