<script setup>
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'

defineProps({
  modelValue: { type: String, default: '' },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
</script>

<template>
  <BaseInput
    :model-value="modelValue"
    name="identifier"
    autocomplete="username"
    input-dir="ltr"
    :label="t('auth.login.fields.identifier')"
    :error="error"
    required
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
