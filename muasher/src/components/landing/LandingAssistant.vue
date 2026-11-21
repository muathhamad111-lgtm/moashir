<script setup>
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useScrollFrame } from '@/composables/landing/useScrollFrame'
import { isOverDeepBand } from '@/utils/landing/deepBands'
import LandingLink from '@/components/landing/LandingLink.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const { t } = useI18n()
const { text, metaText } = useLandingText()

const open = ref(false)
const overDeep = ref(false)
const question = ref('')
const answer = ref(null)
const buttonEl = ref(null)
const inputRef = ref(null)

const items = computed(() => props.section.items ?? [])
const title = computed(() => text(props.section.title))
const placeholder = computed(
  () => metaText(props.section, 'inputLabel') || t('landing.assistant.ask'),
)

useScrollFrame(() => {
  const box = buttonEl.value?.getBoundingClientRect()
  overDeep.value = box ? isOverDeepBand(box.top + box.height / 2) : false
})

async function toggle(next = !open.value) {
  open.value = next
  await nextTick()
  if (next) inputRef.value?.focus()
  else buttonEl.value?.focus()
}

function choose(item) {
  answer.value = {
    key: item.id,
    question: text(item.title),
    body: text(item.description),
    fallback: false,
  }
}

function ask() {
  const asked = question.value.trim()
  if (!asked) return
  answer.value = {
    key: 'asked',
    question: asked,
    body: metaText(props.section, 'fallbackReply'),
    fallback: true,
  }
  question.value = ''
}

function onKeydown(event) {
  if (event.key === 'Escape') toggle(false)
}
</script>

<template>
  <button
    ref="buttonEl"
    class="lp-assist-btn"
    :class="{ 'is-over-deep': overDeep }"
    type="button"
    :aria-expanded="open ? 'true' : 'false'"
    aria-controls="lp-assist"
    :aria-label="title"
    @click="toggle()"
  >
    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lp-i-chat" /></svg>
  </button>
  <div
    v-show="open"
    id="lp-assist"
    class="lp-assist"
    role="dialog"
    aria-labelledby="lp-assist-title"
    @keydown="onKeydown"
  >
    <div class="lp-assist__head">
      <b id="lp-assist-title">{{ title }}</b>
      <button type="button" :aria-label="t('actions.close')" @click="toggle(false)">
        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lp-i-x" /></svg>
      </button>
    </div>
    <p class="lp-assist__desc">{{ text(section.description) }}</p>
    <div v-if="items.length" class="lp-assist__sugs">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        :aria-pressed="answer?.key === item.id ? 'true' : 'false'"
        @click="choose(item)"
      >
        {{ text(item.title) }}
      </button>
    </div>
    <div v-if="answer" class="lp-assist__reply" role="status">
      <b>{{ answer.question }}</b>
      <p>{{ answer.body }}</p>
      <LandingLink
        v-if="answer.fallback && section.ctaUrl"
        class="lp-assist__cta"
        :to="section.ctaUrl"
        @navigate="toggle(false)"
      >
        {{ text(section.ctaLabel) }}
      </LandingLink>
    </div>
    <form class="lp-assist__form" @submit.prevent="ask">
      <input
        ref="inputRef"
        v-model="question"
        type="text"
        autocomplete="off"
        :placeholder="placeholder"
        :aria-label="placeholder"
        maxlength="300"
      />
      <button class="lp-btn lp-btn--fill lp-btn--sm" type="submit">
        {{ t('landing.assistant.send') }}
      </button>
    </form>
  </div>
</template>
