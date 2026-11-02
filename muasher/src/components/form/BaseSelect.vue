<script setup>
import { computed } from 'vue'
import FormField from './FormField.vue'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  label: { type: String, required: true },
  name: { type: String, required: true },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'blur'])

const filled = computed(
  () => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '',
)
</script>

<template>
  <FormField
    v-slot="{ id, describedBy, invalid }"
    :label="label"
    :name="name"
    :error="error"
    :hint="hint"
    :required="required"
    :disabled="disabled"
    :filled="filled"
  >
    <select
      :id="id"
      class="field__control"
      :name="name"
      :disabled="disabled"
      :required="required"
      :aria-invalid="invalid || undefined"
      :aria-describedby="describedBy"
      :value="modelValue"
      @change="emit('update:modelValue', $event.target.value)"
      @blur="emit('blur', $event)"
    >
      <option value="" disabled :selected="modelValue === null || modelValue === ''"></option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
  </FormField>
</template>
