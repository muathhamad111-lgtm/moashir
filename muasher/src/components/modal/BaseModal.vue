<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, default: '' },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  closeOnBackdrop: { type: Boolean, default: true },
})

const emit = defineEmits(['update:modelValue', 'close'])

const { t } = useI18n()
const panelRef = ref(null)
const titleId = useId()
let previouslyFocused = null

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function close() {
  emit('update:modelValue', false)
  emit('close')
}

function focusables() {
  return Array.from(panelRef.value?.querySelectorAll(FOCUSABLE) ?? [])
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.stopPropagation()
    close()
    return
  }
  if (event.key !== 'Tab') return
  const elements = focusables()
  if (!elements.length) {
    event.preventDefault()
    panelRef.value?.focus()
    return
  }
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

function onBackdropClick() {
  if (props.closeOnBackdrop) close()
}

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      previouslyFocused = document.activeElement
      await nextTick()
      const elements = focusables()
      ;(elements[0] ?? panelRef.value)?.focus()
    } else if (previouslyFocused instanceof HTMLElement) {
      previouslyFocused.focus()
      previouslyFocused = null
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (props.modelValue && previouslyFocused instanceof HTMLElement) previouslyFocused.focus()
})
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="modal is-open" :class="`modal--${size}`" @keydown="onKeydown">
      <div class="modal__backdrop" @click="onBackdropClick"></div>
      <div
        ref="panelRef"
        class="modal__panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="title ? titleId : undefined"
        tabindex="-1"
      >
        <header class="modal__header">
          <slot name="header">
            <h2 :id="titleId" class="modal__title">{{ title }}</h2>
          </slot>
          <button
            type="button"
            class="modal__close"
            :aria-label="t('actions.close')"
            @click="close"
          >
            <AppIcon name="x" />
          </button>
        </header>
        <div class="modal__body">
          <slot />
        </div>
        <footer v-if="$slots.footer" class="modal__footer">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>
