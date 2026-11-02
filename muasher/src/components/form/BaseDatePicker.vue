<script setup>
import { computed } from 'vue'
import FormField from './FormField.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, required: true },
  name: { type: String, required: true },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'blur'])

const filled = computed(() => Boolean(props.modelValue))
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
    <input
      :id="id"
      class="field__control datepicker__control"
      type="date"
      :name="name"
      :value="modelValue"
      :min="min || undefined"
      :max="max || undefined"
      :disabled="disabled"
      :required="required"
      :aria-invalid="invalid || undefined"
      :aria-describedby="describedBy"
      @input="emit('update:modelValue', $event.target.value)"
      @blur="emit('blur', $event)"
    />
  </FormField>
</template>
