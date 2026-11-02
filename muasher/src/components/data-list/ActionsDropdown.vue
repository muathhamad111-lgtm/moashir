<script setup>
import { ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisclosure } from '@/composables/useDisclosure'

const props = defineProps({
  actions: { type: Array, required: true },
  label: { type: String, default: '' },
  toggleClass: { type: String, default: 'btn btn--ghost btn--sm btn--icon' },
})

const emit = defineEmits(['select'])

const { t } = useI18n()
const { isOpen, rootRef, close, toggle } = useDisclosure()
const menuId = useId()
const highlightedIndex = ref(-1)
const toggleRef = ref(null)

watch(isOpen, (open) => {
  highlightedIndex.value = open ? 0 : -1
})

function choose(action) {
  if (action.disabled) return
  emit('select', action.key)
  close()
  toggleRef.value?.focus()
}

function moveHighlight(step) {
  if (!props.actions.length) return
  const next = highlightedIndex.value + step
  highlightedIndex.value = Math.min(Math.max(next, 0), props.actions.length - 1)
}

function onKeydown(event) {
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      if (!isOpen.value) toggle()
      else moveHighlight(1)
      break
    case 'ArrowUp':
      event.preventDefault()
      if (isOpen.value) moveHighlight(-1)
      break
    case 'Enter':
    case ' ':
      if (isOpen.value) {
        event.preventDefault()
        const action = props.actions[highlightedIndex.value]
        if (action) choose(action)
      }
      break
  }
}
</script>

<template>
  <div ref="rootRef" class="dropdown actions-dropdown" :class="{ 'is-open': isOpen }">
    <button
      ref="toggleRef"
      type="button"
      :class="toggleClass"
      aria-haspopup="menu"
      :aria-expanded="isOpen"
      :aria-controls="menuId"
      :aria-label="label || t('components.actions.label')"
      @click="toggle"
      @keydown="onKeydown"
    >
      <slot name="trigger">
        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
      </slot>
    </button>
    <ul v-if="isOpen" :id="menuId" class="dropdown__menu" role="menu">
      <li v-if="$slots.header" class="actions-dropdown__header" role="presentation">
        <slot name="header"></slot>
      </li>
      <li v-for="(action, index) in actions" :key="action.key" role="presentation">
        <button
          type="button"
          class="dropdown__item actions-dropdown__item"
          :class="{ 'is-danger': action.variant === 'danger', 'is-highlighted': index === highlightedIndex }"
          role="menuitem"
          tabindex="-1"
          :disabled="action.disabled || undefined"
          @click="choose(action)"
          @mousemove="highlightedIndex = index"
        >
          <i v-if="action.icon" :class="action.icon" aria-hidden="true"></i>
          {{ action.label }}
        </button>
      </li>
    </ul>
  </div>
</template>
