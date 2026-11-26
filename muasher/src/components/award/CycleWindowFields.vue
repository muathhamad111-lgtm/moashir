<script setup>
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import CycleStepCounter from './CycleStepCounter.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import { formatHijriDate, startOfRiyadhDay } from '@/utils/datetime'

const props = defineProps({
  locked: { type: Object, required: true },
})

const FIELDS = [
  { name: 'submissionStart', required: true },
  { name: 'submissionEnd', required: true },
  { name: 'committeeStart', required: true },
  { name: 'committeeEnd', required: true },
  { name: 'announcementAt', required: false },
]
const DATE_KEY = /^\d{4}-\d{2}-\d{2}$/

const { t, locale } = useI18n()
const fields = FIELDS.map((field) => ({ ...field, state: useField(field.name) }))

function hijriOf(value) {
  return DATE_KEY.test(value ?? '') ? formatHijriDate(startOfRiyadhDay(value), locale.value) : ''
}

function hintOf(field) {
  const lockedHint = props.locked.has(field.name) ? t('cycles.form.lockedHint') : ''
  return [lockedHint, hijriOf(field.state.value.value)].filter(Boolean).join('\n')
}
</script>

<template>
  <fieldset class="cycle-form__section">
    <legend class="cycle-form__legend">
      {{ t('cycles.form.sections.windows') }}
      <CycleStepCounter />
    </legend>
    <p class="cycle-form__hint">{{ t('cycles.form.windowsHint') }}</p>
    <div class="cycle-form__grid cycle-form__grid--dates">
      <BaseDatePicker
        v-for="field in fields"
        :key="field.name"
        v-model="field.state.value.value"
        :name="field.name"
        :label="t(`cycles.form.${field.name}`)"
        :error="field.state.errorMessage.value"
        :required="field.required"
        :disabled="locked.has(field.name)"
        :hint="hintOf(field)"
        @blur="field.state.handleBlur"
      />
    </div>
  </fieldset>
</template>
