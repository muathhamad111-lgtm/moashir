<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { MODAL_ROLES, MODAL_TONES } from '@/constants/modal'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  closeOnBackdrop: { type: Boolean, default: true },
  role: {
    type: String,
    default: MODAL_ROLES.DIALOG,
    validator: (value) => Object.values(MODAL_ROLES).includes(value),
  },
  icon: { type: String, default: '' },
  tone: {
    type: String,
    default: MODAL_TONES.CONFIRM,
    validator: (value) => Object.values(MODAL_TONES).includes(value),
  },
  describedBy: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'close', 'interact'])

const { t } = useI18n()
const panelRef = ref(null)
const titleId = useId()
const subtitleId = useId()
let previouslyFocused = null

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

const isCentered = computed(() => Boolean(props.icon))
const description = computed(
  () => props.describedBy || (props.subtitle ? subtitleId : undefined),
)

function close() {
  emit('update:modelValue', false)
  emit('close')
}

function focusables() {
  return Array.from(panelRef.value?.querySelectorAll(FOCUSABLE) ?? [])
}

function onKeydown(event) {
  emit('interact')
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
      const preferred = panelRef.value?.querySelector('[data-autofocus]')
      ;(preferred ?? focusables()[0] ?? panelRef.value)?.focus()
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
    <div
      v-if="modelValue"
      class="modal is-open"
      :class="[`modal--${size}`, { 'modal--centered': isCentered }]"
      @keydown="onKeydown"
    >
      <div class="modal__backdrop" @click="onBackdropClick"></div>
      <div
        ref="panelRef"
        class="modal__panel"
        :role="role"
        aria-modal="true"
        :aria-labelledby="title ? titleId : undefined"
        :aria-describedby="description"
        tabindex="-1"
        @pointerenter="emit('interact')"
      >
        <header class="modal__header">
          <span v-if="icon" class="modal__icon" :class="`modal__icon--${tone}`" aria-hidden="true">
            <AppIcon :name="icon" size="lg" />
          </span>
          <slot name="header">
            <div class="modal__heading">
              <h2 :id="titleId" class="modal__title">{{ title }}</h2>
              <p v-if="subtitle" :id="subtitleId" class="modal__subtitle">
                <bdi>{{ subtitle }}</bdi>
              </p>
            </div>
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
        <div v-if="$slots.default" class="modal__body">
          <slot />
        </div>
        <footer v-if="$slots.footer" class="modal__footer">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>
