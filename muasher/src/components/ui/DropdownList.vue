<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useDisclosure } from '@/composables/useDisclosure'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { isOpen, rootRef, close, toggle } = useDisclosure()
const listId = useId()
const highlightedIndex = ref(-1)
const toggleRef = ref(null)

const selected = computed(() => props.options.find((option) => option.value === props.modelValue))

watch(isOpen, (open) => {
  if (open) {
    const selectedIndex = props.options.findIndex((option) => option.value === props.modelValue)
    highlightedIndex.value = selectedIndex === -1 ? 0 : selectedIndex
  }
})

function onToggleClick() {
  if (props.disabled) return
  toggle()
}

function select(option) {
  emit('update:modelValue', option.value)
  close()
  toggleRef.value?.focus()
}

function moveHighlight(step) {
  if (!props.options.length) return
  const next = highlightedIndex.value + step
  highlightedIndex.value = Math.min(Math.max(next, 0), props.options.length - 1)
}

function onKeydown(event) {
  if (props.disabled) return
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
    case 'Home':
      if (isOpen.value) {
        event.preventDefault()
        highlightedIndex.value = 0
      }
      break
    case 'End':
      if (isOpen.value) {
        event.preventDefault()
        highlightedIndex.value = props.options.length - 1
      }
      break
    case 'Enter':
    case ' ':
      if (isOpen.value) {
        event.preventDefault()
        const option = props.options[highlightedIndex.value]
        if (option) select(option)
      }
      break
  }
}
</script>

<template>
  <div
    ref="rootRef"
    class="dropdown"
    :class="{ 'is-open': isOpen, 'dropdown--disabled': disabled }"
  >
    <button
      ref="toggleRef"
      type="button"
      class="dropdown__toggle"
      :disabled="disabled"
      aria-haspopup="listbox"
      :aria-expanded="isOpen"
      :aria-controls="listId"
      @click="onToggleClick"
      @keydown="onKeydown"
    >
      <span v-if="selected">{{ selected.label }}</span>
      <span v-else class="dropdown__placeholder">{{ placeholder }}</span>
      <i class="fa-solid fa-chevron-down dropdown__caret" aria-hidden="true"></i>
    </button>
    <ul v-if="isOpen" :id="listId" class="dropdown__menu" role="listbox">
      <li v-for="(option, index) in options" :key="option.value" role="presentation">
        <button
          type="button"
          class="dropdown__item"
          :class="{
            'is-active': option.value === modelValue,
            'is-highlighted': index === highlightedIndex,
          }"
          role="option"
          :aria-selected="option.value === modelValue"
          tabindex="-1"
          @click="select(option)"
          @mousemove="highlightedIndex = index"
        >
          {{ option.label }}
        </button>
      </li>
    </ul>
  </div>
</template>
