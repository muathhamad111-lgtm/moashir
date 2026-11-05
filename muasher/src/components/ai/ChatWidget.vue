<script setup>
import { nextTick, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAiChat } from '@/composables/useAiChat'
import ChatMessage from './ChatMessage.vue'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'
import { MOCK_MODULES } from '@/constants/mockModules'

const { t } = useI18n()
const { availability, messages, sending, lastError, localized, send, retry } = useAiChat()

const isOpen = ref(false)
const draft = ref('')
const panelRef = ref(null)
const inputRef = ref(null)
const threadRef = ref(null)
const launcherRef = ref(null)
const panelId = useId()
const titleId = useId()

const SUGGESTION_KEYS = ['whatIs', 'paths', 'criteria', 'register']

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function focusables() {
  return Array.from(panelRef.value?.querySelectorAll(FOCUSABLE) ?? [])
}

async function open() {
  isOpen.value = true
  await nextTick()
  inputRef.value?.focus()
}

function close() {
  isOpen.value = false
  launcherRef.value?.focus()
}

function toggle() {
  if (isOpen.value) close()
  else open()
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.stopPropagation()
    close()
    return
  }
  if (event.key !== 'Tab') return
  const elements = focusables()
  if (!elements.length) return
  const first = elements[0]
  const last = elements[elements.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

async function scrollToEnd() {
  await nextTick()
  const thread = threadRef.value
  if (thread) thread.scrollTop = thread.scrollHeight
}

async function submit() {
  const question = draft.value.trim()
  if (!question || sending.value || availability.value !== 'active') return
  draft.value = ''
  await send(question)
}

function askSuggestion(key) {
  draft.value = t(`ai.suggestions.${key}`)
  submit()
}

watch(
  () => messages.value.length,
  () => scrollToEnd(),
)
watch(sending, () => scrollToEnd())

const DEMO_MODULES = [MOCK_MODULES.AI]
</script>

<template>
  <div class="chat-widget">
    <button
      ref="launcherRef"
      type="button"
      class="chat-widget__launcher"
      :class="{ 'is-open': isOpen }"
      :aria-expanded="isOpen"
      :aria-controls="panelId"
      :aria-label="isOpen ? $t('ai.close') : $t('ai.launcherLabel')"
      @click="toggle"
    >
      <i :class="isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-comment-dots'" aria-hidden="true"></i>
    </button>

    <section
      v-if="isOpen"
      :id="panelId"
      ref="panelRef"
      class="chat-widget__panel"
      role="dialog"
      aria-modal="false"
      :aria-labelledby="titleId"
      @keydown="onKeydown"
    >
      <header class="chat-widget__header">
        <div class="chat-widget__heading">
          <span class="chat-widget__avatar" aria-hidden="true">
            <i class="fa-solid fa-robot"></i>
          </span>
          <div>
            <h2 :id="titleId" class="chat-widget__title">{{ $t('ai.title') }}</h2>
            <p class="chat-widget__subtitle">{{ $t('ai.subtitle') }}</p>
          </div>
        </div>
        <button
          type="button"
          class="chat-widget__close"
          :aria-label="$t('ai.close')"
          @click="close"
        >
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </header>

      <div ref="threadRef" class="chat-widget__thread">
        <DemoDataNotice scope="section" :modules="DEMO_MODULES" />
        <p class="chat-widget__disclaimer">
          <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
          {{ $t('ai.disclaimer') }}
        </p>

        <div v-if="availability === 'loading'" class="chat-widget__status" role="status">
          <span class="chat-widget__spinner" aria-hidden="true"></span>
          {{ $t('ai.checking') }}
        </div>

        <div
          v-else-if="availability === 'inactive'"
          class="chat-widget__status chat-widget__status--inactive"
          role="status"
        >
          <i class="fa-solid fa-power-off" aria-hidden="true"></i>
          {{ $t('ai.inactive') }}
        </div>

        <div
          v-else-if="availability === 'error'"
          class="chat-widget__status chat-widget__status--error"
          role="alert"
        >
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <span>{{ $t('ai.unavailable') }}</span>
          <button type="button" class="chat-widget__inline-retry" @click="retry">
            {{ $t('common.retry') }}
          </button>
        </div>

        <template v-else>
          <div v-if="messages.length === 0" class="chat-widget__welcome">
            <p class="chat-widget__welcome-text">{{ $t('ai.welcome') }}</p>
            <ul class="chat-widget__suggestions">
              <li v-for="key in SUGGESTION_KEYS" :key="key">
                <button
                  type="button"
                  class="chat-widget__suggestion"
                  :disabled="sending"
                  @click="askSuggestion(key)"
                >
                  {{ $t(`ai.suggestions.${key}`) }}
                </button>
              </li>
            </ul>
          </div>

          <ul v-else class="chat-widget__messages" aria-live="polite" aria-atomic="false">
            <ChatMessage
              v-for="message in messages"
              :key="message.id"
              :message="message"
              :localized="localized"
            />
          </ul>

          <div v-if="sending" class="chat-widget__typing" role="status" aria-live="polite">
            <span class="chat-widget__typing-label">{{ $t('ai.typing') }}</span>
            <span class="chat-widget__dots" aria-hidden="true">
              <span></span><span></span><span></span>
            </span>
          </div>

          <div v-if="lastError" class="chat-widget__turn-error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>{{ $t('ai.turnError') }}</span>
            <button
              type="button"
              class="chat-widget__inline-retry"
              :disabled="sending"
              @click="retry"
            >
              {{ $t('common.retry') }}
            </button>
          </div>
        </template>
      </div>

      <form class="chat-widget__composer" @submit.prevent="submit">
        <label class="chat-widget__composer-label" :for="`${panelId}-input`">
          {{ $t('ai.inputLabel') }}
        </label>
        <div class="chat-widget__composer-row">
          <input
            :id="`${panelId}-input`"
            ref="inputRef"
            v-model="draft"
            type="text"
            class="chat-widget__input"
            :placeholder="$t('ai.placeholder')"
            :disabled="availability !== 'active' || sending"
            autocomplete="off"
          />
          <button
            type="submit"
            class="chat-widget__send"
            :disabled="availability !== 'active' || sending || !draft.trim()"
            :aria-label="$t('ai.send')"
          >
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
          </button>
        </div>
      </form>
    </section>
  </div>
</template>
