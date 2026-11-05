<script setup>
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  message: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const isUser = computed(() => props.message.role === 'user')
const isMiss = computed(() => props.message.kind === 'miss')

const text = computed(() => {
  if (isMiss.value) return ''
  return props.localized(props.message.content)
})
</script>

<template>
  <li
    class="chat-message"
    :class="[
      isUser ? 'chat-message--user' : 'chat-message--assistant',
      isMiss && 'chat-message--miss',
    ]"
  >
    <span class="chat-message__role" aria-hidden="true">
      <AppIcon :name="isUser ? 'user' : 'robot'" />
    </span>
    <div class="chat-message__bubble">
      <span class="chat-message__sender">
        {{ isUser ? $t('ai.you') : $t('ai.assistant') }}
      </span>
      <p v-if="isMiss" class="chat-message__text chat-message__text--miss">
        <AppIcon name="info-circle" />
        {{ $t('ai.kbMiss') }}
      </p>
      <p v-else class="chat-message__text">{{ text }}</p>
    </div>
  </li>
</template>
