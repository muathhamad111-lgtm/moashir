<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import BaseTextarea from '@/components/form/BaseTextarea.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  rows: { type: Number, default: 3 },
  required: { type: Boolean, default: false },
})

const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))
</script>

<template>
  <BaseTextarea
    v-model="value"
    :name="name"
    :label="label"
    :hint="hint"
    :rows="rows"
    input-dir="auto"
    :error="errorMessage"
    :required="required"
    @blur="handleBlur"
  />
</template>
