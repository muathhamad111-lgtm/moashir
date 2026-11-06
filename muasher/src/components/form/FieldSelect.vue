<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import AppSelect from '@/components/form/AppSelect.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  options: { type: Array, required: true },
  placeholder: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))
</script>

<template>
  <AppSelect
    v-model="value"
    :name="name"
    :label="label"
    :options="options"
    :placeholder="placeholder"
    :hint="hint"
    :error="errorMessage"
    :required="required"
    :disabled="disabled"
    @blur="handleBlur"
  />
</template>
