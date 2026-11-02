<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import BaseSelect from '@/components/form/BaseSelect.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  options: { type: Array, required: true },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))
</script>

<template>
  <BaseSelect
    v-model="value"
    :name="name"
    :label="label"
    :options="options"
    :hint="hint"
    :error="errorMessage"
    :required="required"
    :disabled="disabled"
    @blur="handleBlur"
  />
</template>
