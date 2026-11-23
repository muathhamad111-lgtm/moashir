<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import { normalizeHex } from '@/utils/brandingContrast'

const props = defineProps({
  role: {
    type: String,
    required: true,
    validator: (value) => ['top', 'bottom'].includes(value),
  },
  // null while the colour follows the brand colours.
  modelValue: { type: String, default: null },
  autoValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'restore-auto'])
const { t } = useI18n()

const isAuto = computed(() => props.modelValue === null)
const label = computed(() => t(`branding.rail.${props.role}.label`))
const shown = computed(() => (isAuto.value ? props.autoValue : props.modelValue))
const pickerValue = computed(() => normalizeHex(shown.value) ?? props.autoValue)
const error = computed(() =>
  isAuto.value || normalizeHex(props.modelValue) ? '' : t('branding.colors.invalid'),
)
</script>

<template>
  <div class="brand-rail-field" :data-role="role">
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
      :model-value="shown"
      :label="label"
      :name="`sidebar_${role}_color`"
      :hint="t(`branding.rail.${role}.hint`)"
      :error="error"
      :disabled="disabled"
      input-dir="ltr"
      autocomplete="off"
      @update:model-value="emit('update:modelValue', $event)"
    />
    <div class="brand-rail-field__state">
      <Badge :variant="isAuto ? 'muted' : 'info'">
        {{ t(isAuto ? 'branding.derived.auto' : 'branding.derived.custom') }}
      </Badge>
      <BaseButton
        v-if="!isAuto"
        variant="ghost"
        size="sm"
        :disabled="disabled"
        @click="emit('restore-auto')"
      >
        {{ t('branding.derived.restoreAuto') }}
        <span class="brand-derived__sr">{{ label }}</span>
      </BaseButton>
    </div>
  </div>
</template>
