<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { MESSAGE_AUTHORS } from '@/constants/messages'
import { formatDate } from '@/utils/datetime'

const props = defineProps({
  entry: { type: Object, required: true },
  senderName: { type: String, default: '' },
})

const { t, locale } = useI18n()
const isSender = computed(() => props.entry.author === MESSAGE_AUTHORS.SENDER)
const author = computed(() =>
  isSender.value ? props.senderName || t('portal.messages.you') : t('portal.messages.team'),
)
const initial = computed(() => author.value.trim().charAt(0))
const time = computed(() =>
  formatDate(props.entry.createdAt, locale.value, {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: 'Asia/Riyadh',
  }),
)
</script>

<template>
  <li class="message-bubble" :class="isSender ? 'message-bubble--sender' : 'message-bubble--team'">
    <div class="message-bubble__head">
      <span class="message-bubble__avatar" aria-hidden="true">{{ initial }}</span>
      <span class="message-bubble__author">{{ author }}</span>
      <time class="message-bubble__time" :datetime="entry.createdAt" dir="ltr">{{ time }}</time>
    </div>
    <p class="message-bubble__body">{{ entry.body }}</p>
  </li>
</template>
