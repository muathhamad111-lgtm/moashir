<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import { normalizeHex } from '@/utils/brandingContrast'

const props = defineProps({
  role: {
    type: String,
    required: true,
    validator: (value) => ['primary', 'secondary'].includes(value),
  },
  modelValue: { type: String, required: true },
  fallback: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const label = computed(() => t(`branding.colors.${props.role}.label`))
const hex = computed(() => normalizeHex(props.modelValue))
const pickerValue = computed(() => hex.value ?? props.fallback)
const error = computed(() => (hex.value ? '' : t('branding.colors.invalid')))
</script>

<template>
  <div class="brand-color-field" :data-role="role">
    <input
      class="brand-color-field__picker"
      type="color"
      :value="pickerValue"
      :disabled="disabled"
      :aria-label="t('branding.colors.picker', { role: label })"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <BaseInput
      class="brand-color-field__input"
      :model-value="modelValue"
      :label="label"
      :name="`${role}_color`"
      :hint="t(`branding.colors.${role}.hint`)"
      :error="error"
      :disabled="disabled"
      input-dir="ltr"
      autocomplete="off"
      required
      @update:model-value="emit('update:modelValue', $event)"
    />
  </div>
</template>
