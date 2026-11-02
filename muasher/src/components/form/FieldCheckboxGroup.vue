<script setup>
import { computed, toRef, useId } from 'vue'
import { useField } from 'vee-validate'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  options: { type: Array, required: true },
  hint: { type: String, default: '' },
})

const { value, errorMessage } = useField(toRef(props, 'name'))
const baseId = useId()
const selected = computed(() => (Array.isArray(value.value) ? value.value : []))

function toggle(option, checked) {
  const others = selected.value.filter((item) => item !== option.value)
  value.value = checked ? [...others, option.value] : others
}
</script>

<template>
  <fieldset class="checkbox-group" :class="{ 'field--error': errorMessage }" :aria-describedby="errorMessage ? `${baseId}-error` : hint ? `${baseId}-hint` : undefined">
    <legend class="checkbox-group__legend">{{ label }}</legend>
    <div class="checkbox-group__options">
      <BaseCheckbox
        v-for="option in options"
        :key="option.value"
        :name="`${name}-${option.value}`"
        :label="option.label"
        :model-value="selected.includes(option.value)"
        :disabled="option.disabled"
        @update:model-value="toggle(option, $event)"
      />
    </div>
    <p v-if="hint && !errorMessage" :id="`${baseId}-hint`" class="field__hint">{{ hint }}</p>
    <p v-if="errorMessage" :id="`${baseId}-error`" class="field__error" role="alert">{{ errorMessage }}</p>
  </fieldset>
</template>
