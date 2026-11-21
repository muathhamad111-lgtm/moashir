<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import SecretInput from '@/components/settings/SecretInput.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  meta: { type: Object, default: null },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const { value, errorMessage } = useField(toRef(props, 'name'))
</script>

<template>
  <SecretInput
    v-model="value"
    :name="name"
    :label="label"
    :meta="meta"
    :hint="hint"
    :error="errorMessage"
    :disabled="disabled"
  />
</template>
