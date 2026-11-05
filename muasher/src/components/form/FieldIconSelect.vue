<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useField } from 'vee-validate'
import SearchSelect from '@/components/form/SearchSelect.vue'
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
const { value, errorMessage } = useField(toRef(props, 'name'))

const choices = computed(() => [
  ...(props.optional ? [{ value: '', label: t('cms.fields.noIcon') }] : []),
  ...props.options.map((icon) => ({ value: icon, label: icon })),
])
</script>

<template>
  <div class="icon-field">
    <div class="icon-field__row">
      <span class="icon-field__preview" aria-hidden="true">
        <AppIcon :name="value" />
      </span>
      <SearchSelect
        v-model="value"
        class="icon-field__select"
        :options="choices"
        :label="label"
        :disabled="disabled"
      >
        <template #option="{ option }">
          <AppIcon v-if="option.value" :name="option.value" class="icon-field__option-icon" />
          {{ option.label }}
        </template>
      </SearchSelect>
    </div>
    <p v-if="errorMessage" class="icon-field__error" role="alert">
      {{ errorMessage }}
    </p>
    <p v-else-if="hint" class="icon-field__hint">{{ hint }}</p>
  </div>
</template>
