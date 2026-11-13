<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useField } from 'vee-validate'
import { iconLabelKey } from '@/utils/icons/iconLabel'
import AppCombobox from '@/components/form/AppCombobox.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  options: { type: Array, required: true },
  hint: { type: String, default: '' },
  optional: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()
const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))

const choices = computed(() => [
  ...(props.optional ? [{ value: '', label: t('cms.fields.noIcon') }] : []),
  ...props.options.map((icon) => ({
    value: icon,
    label: t(iconLabelKey(icon)),
    description: icon,
    icon,
  })),
])
</script>

<template>
  <div class="icon-field">
    <span class="icon-field__preview" aria-hidden="true">
      <AppIcon :name="value" />
    </span>
    <AppCombobox
      v-model="value"
      class="icon-field__select"
      :name="name"
      :options="choices"
      :label="label"
      :hint="hint"
      :error="errorMessage"
      :disabled="disabled"
      @blur="handleBlur"
    />
  </div>
</template>
