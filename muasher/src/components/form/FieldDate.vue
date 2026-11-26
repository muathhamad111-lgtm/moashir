<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))
</script>

<template>
  <BaseDatePicker
    v-model="value"
    :name="name"
    :label="label"
    :min="min"
    :max="max"
    :hint="hint"
    :error="errorMessage"
    :required="required"
    :disabled="disabled"
    @blur="handleBlur"
  />
</template>
