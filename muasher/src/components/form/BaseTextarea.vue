<script setup>
import FormField from './FormField.vue'

defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, required: true },
  name: { type: String, required: true },
  rows: { type: Number, default: 4 },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  inputDir: { type: String, default: undefined },
})

const emit = defineEmits(['update:modelValue', 'blur'])
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
    :filled="Boolean(String(modelValue).length)"
  >
    <textarea
      :id="id"
      class="field__control"
      :name="name"
      :rows="rows"
      :dir="inputDir"
      :value="modelValue"
      placeholder=" "
      :disabled="disabled"
      :required="required"
      :aria-invalid="invalid || undefined"
      :aria-describedby="describedBy"
      @input="emit('update:modelValue', $event.target.value)"
      @blur="emit('blur', $event)"
    ></textarea>
  </FormField>
</template>
