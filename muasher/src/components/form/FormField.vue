<script setup>
import { computed, ref, useId } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  name: { type: String, required: true },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  filled: { type: Boolean, default: false },
})

const controlId = useId()
const errorId = computed(() => `${controlId}-error`)
const hintId = computed(() => `${controlId}-hint`)
const focused = ref(false)

const describedBy = computed(() => {
  const ids = []
  if (props.error) ids.push(errorId.value)
  if (props.hint) ids.push(hintId.value)
  return ids.length ? ids.join(' ') : undefined
})

const classes = computed(() => [
  'field',
  props.error && 'field--error',
  props.disabled && 'field--disabled',
  props.required && 'field--required',
  focused.value && 'field--focused',
  (focused.value || props.filled) && 'field--floated',
])
</script>

<template>
  <div :class="classes" @focusin="focused = true" @focusout="focused = false">
    <slot :id="controlId" :described-by="describedBy" :invalid="Boolean(error)" />
    <label class="field__label" :for="controlId">{{ label }}</label>
    <p v-if="hint && !error" :id="hintId" class="field__hint">{{ hint }}</p>
    <p v-if="error" :id="errorId" class="field__error" role="alert">{{ error }}</p>
  </div>
</template>
