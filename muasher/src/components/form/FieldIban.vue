<script setup>
import { computed, toRef } from 'vue'
import { useField } from 'vee-validate'
import BaseInput from '@/components/form/BaseInput.vue'
import { formatIban } from '@/utils/payments/iban'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
})

const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))

const formatted = computed({
  get: () => formatIban(value.value),
  set: (next) => {
    value.value = formatIban(next)
  },
})
</script>

<template>
  <BaseInput
    v-model="formatted"
    :name="name"
    :label="label"
    :hint="hint"
    :error="errorMessage"
    :required="required"
    input-dir="ltr"
    autocomplete="off"
    inputmode="text"
    @blur="handleBlur"
  />
</template>
