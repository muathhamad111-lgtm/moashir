<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisclosure } from '@/composables/useDisclosure'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: [String, Number, Array, null], default: null },
  options: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: false },
  searchable: { type: Boolean, default: true },
  remote: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  label: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'search'])

const { t } = useI18n()
const { isOpen, rootRef, open, close } = useDisclosure()
const query = ref('')
const highlightedIndex = ref(-1)
const inputRef = ref(null)
const listId = useId()
const inputId = useId()

const selectedValues = computed(() => {
  if (props.multiple) return Array.isArray(props.modelValue) ? props.modelValue : []
  return props.modelValue === null || props.modelValue === '' ? [] : [props.modelValue]
})

const selectedOptions = computed(() =>
  selectedValues.value
    .map((value) => props.options.find((option) => option.value === value))
    .filter(Boolean),
)

const filteredOptions = computed(() => {
  if (props.remote || !props.searchable || !query.value) return props.options
  const needle = query.value.toLowerCase()
  return props.options.filter((option) => option.label.toLowerCase().includes(needle))
})

const singleLabel = computed(() =>
  !props.multiple && selectedOptions.value.length ? selectedOptions.value[0].label : '',
)

const floated = computed(
  () => isOpen.value || selectedOptions.value.length > 0 || Boolean(query.value),
)

watch(query, (value) => {
  if (props.remote) emit('search', value)
  highlightedIndex.value = value ? 0 : -1
})

watch(isOpen, (openState) => {
  if (!openState) {
    query.value = ''
    highlightedIndex.value = -1
  }
})

function isSelected(option) {
  return selectedValues.value.includes(option.value)
}

function focusControl() {
  if (props.disabled) return
  open()
  inputRef.value?.focus()
}

function select(option) {
  if (props.multiple) {
    const next = isSelected(option)
      ? selectedValues.value.filter((value) => value !== option.value)
      : [...selectedValues.value, option.value]
    emit('update:modelValue', next)
    query.value = ''
    inputRef.value?.focus()
    return
  }
  emit('update:modelValue', option.value)
  close()
}

function removeTag(option) {
  emit(
    'update:modelValue',
    selectedValues.value.filter((value) => value !== option.value),
  )
}

function moveHighlight(step) {
  const total = filteredOptions.value.length
  if (!total) return
  if (!isOpen.value) open()
  const next = highlightedIndex.value + step
  highlightedIndex.value = Math.min(Math.max(next, 0), total - 1)
}

function onInputKeydown(event) {
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      moveHighlight(1)
      break
    case 'ArrowUp':
      event.preventDefault()
      moveHighlight(-1)
      break
    case 'Enter': {
      event.preventDefault()
      const option = filteredOptions.value[highlightedIndex.value]
      if (isOpen.value && option) select(option)
      break
    }
    case 'Backspace':
      if (props.multiple && !query.value && selectedOptions.value.length) {
        removeTag(selectedOptions.value[selectedOptions.value.length - 1])
      }
      break
    case 'Tab':
      close()
      break
  }
}
</script>

<template>
  <div
    ref="rootRef"
    class="select2-search"
    :class="{ 'is-open': isOpen, 'is-disabled': disabled, 'is-floated': floated }"
  >
    <label v-if="label" class="select2-search__label" :for="inputId">{{ label }}</label>
    <div class="select2-search__control" @click="focusControl">
      <span
        v-for="option in multiple ? selectedOptions : []"
        :key="option.value"
        class="select2-search__tag"
      >
        {{ option.label }}
        <button
          type="button"
          class="select2-search__tag-remove"
          :aria-label="t('components.searchSelect.removeTag', { label: option.label })"
          @click.stop="removeTag(option)"
        >
          <AppIcon name="x" />
        </button>
      </span>
      <span v-if="!multiple && singleLabel && !isOpen" class="select2-search__value">
        {{ singleLabel }}
      </span>
      <input
        :id="inputId"
        ref="inputRef"
        v-model="query"
        class="select2-search__input"
        type="text"
        role="combobox"
        :aria-expanded="isOpen"
        :aria-controls="listId"
        aria-autocomplete="list"
        :aria-label="label || placeholder || t('components.searchSelect.search')"
        placeholder=" "
        :disabled="disabled"
        :readonly="!searchable"
        @focus="open"
        @keydown="onInputKeydown"
      />
      <AppIcon name="chevron-down" class="select2-search__caret" />
    </div>
    <ul
      v-if="isOpen"
      :id="listId"
      class="select2-search__menu"
      role="listbox"
      :aria-multiselectable="multiple || undefined"
    >
      <li v-if="loading" class="select2-search__empty" role="presentation">
        {{ t('components.searchSelect.loading') }}
      </li>
      <li v-else-if="!filteredOptions.length" class="select2-search__empty" role="presentation">
        {{ t('components.searchSelect.noResults') }}
      </li>
      <li
        v-for="(option, index) in loading ? [] : filteredOptions"
        :key="option.value"
        role="presentation"
      >
        <button
          type="button"
          class="select2-search__option"
          :class="{
            'is-selected': isSelected(option),
            'is-highlighted': index === highlightedIndex,
          }"
          role="option"
          :aria-selected="isSelected(option)"
          tabindex="-1"
          @click="select(option)"
          @mousemove="highlightedIndex = index"
        >
          <slot name="option" :option="option">{{ option.label }}</slot>
          <span v-if="option.badge" class="select2-search__badge">{{ option.badge }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
