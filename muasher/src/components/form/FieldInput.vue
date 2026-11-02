<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import BaseInput from '@/components/form/BaseInput.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  inputmode: { type: String, default: undefined },
  autocomplete: { type: String, default: undefined },
  inputDir: { type: String, default: undefined },
})

const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))
</script>

<template>
  <BaseInput
    v-model="value"
    :name="name"
    :type="type"
    :label="label"
    :hint="hint"
    :error="errorMessage"
    :required="required"
    :disabled="disabled"
    :inputmode="inputmode"
    :autocomplete="autocomplete"
    :input-dir="inputDir"
    @blur="handleBlur"
  />
</template>
