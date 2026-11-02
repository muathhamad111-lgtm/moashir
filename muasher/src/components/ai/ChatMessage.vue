<script setup>
import { computed } from 'vue'

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
      <i :class="isUser ? 'fa-solid fa-user' : 'fa-solid fa-robot'"></i>
    </span>
    <div class="chat-message__bubble">
      <span class="chat-message__sender">
        {{ isUser ? $t('ai.you') : $t('ai.assistant') }}
      </span>
      <p v-if="isMiss" class="chat-message__text chat-message__text--miss">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        {{ $t('ai.kbMiss') }}
      </p>
      <p v-else class="chat-message__text">{{ text }}</p>
    </div>
  </li>
</template>
