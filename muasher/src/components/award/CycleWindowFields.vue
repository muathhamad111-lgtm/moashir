<script setup>
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'

defineProps({
  locked: { type: Object, required: true },
})

const FIELDS = [
  { name: 'submissionStart', required: true },
  { name: 'submissionEnd', required: true },
  { name: 'committeeStart', required: true },
  { name: 'committeeEnd', required: true },
  { name: 'announcementAt', required: false },
]

const { t } = useI18n()
const fields = FIELDS.map((field) => ({ ...field, state: useField(field.name) }))
</script>

<template>
  <fieldset class="cycle-form__section">
    <legend class="cycle-form__legend">{{ t('cycles.form.sections.windows') }}</legend>
    <p class="cycle-form__hint">{{ t('cycles.form.windowsHint') }}</p>
    <div class="cycle-form__grid">
      <BaseDatePicker
        v-for="field in fields"
        :key="field.name"
        v-model="field.state.value.value"
        :name="field.name"
        :label="t(`cycles.form.${field.name}`)"
        :error="field.state.errorMessage.value"
        :required="field.required"
        :disabled="locked.has(field.name)"
        :hint="locked.has(field.name) ? t('cycles.form.lockedHint') : ''"
        @blur="field.state.handleBlur"
      />
    </div>
  </fieldset>
</template>
