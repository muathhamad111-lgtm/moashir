<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'

defineProps({
  name: { type: String, default: 'nationalId' },
  label: { type: String, default: '' },
  required: { type: Boolean, default: true },
})

const { t } = useI18n()
</script>

<template>
  <FieldInput
    :name="name"
    :label="label || t('nationalIdentity.field.label')"
    :hint="t('nationalIdentity.field.purpose')"
    :required="required"
    inputmode="numeric"
    autocomplete="off"
    input-dir="ltr"
  />
</template>
