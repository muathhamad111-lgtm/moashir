<script setup>
import { computed, useId } from 'vue'

defineProps({
  modelValue: { type: [String, Number, null], default: null },
  label: { type: String, required: true },
  name: { type: String, required: true },
  options: { type: Array, required: true },
  inline: { type: Boolean, default: false },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const baseId = useId()
const errorId = computed(() => `${baseId}-error`)

function optionId(option) {
  return `${baseId}-${option.value}`
}
</script>

<template>
  <fieldset
    class="radio-group"
    :class="{ 'radio-group--inline': inline, 'field--error': error }"
    :disabled="disabled"
    :aria-describedby="error ? errorId : undefined"
  >
    <legend class="radio-group__legend">{{ label }}</legend>
    <div class="radio-group__options">
      <label
        v-for="option in options"
        :key="option.value"
        class="radio"
        :class="{ 'is-disabled': disabled || option.disabled }"
        :for="optionId(option)"
      >
        <input
          :id="optionId(option)"
          class="radio__input"
          type="radio"
          :name="name"
          :value="option.value"
          :checked="option.value === modelValue"
          :disabled="disabled || option.disabled"
          @change="emit('update:modelValue', option.value)"
        />
        <span class="radio__label">{{ option.label }}</span>
      </label>
    </div>
    <p v-if="error" :id="errorId" class="field__error" role="alert">{{ error }}</p>
  </fieldset>
</template>
