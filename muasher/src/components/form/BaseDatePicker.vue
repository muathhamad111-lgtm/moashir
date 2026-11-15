<script setup>
import { computed, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import FormField from './FormField.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useDateSegments } from '@/composables/useDateSegments'
import { DATE_SEGMENTS } from '@/utils/dateSegments'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, required: true },
  name: { type: String, required: true },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'blur'])

const { t } = useI18n()
const native = ref(null)
const {
  segments,
  incomplete,
  touched,
  bindInput,
  onSegmentInput,
  onSegmentKeydown,
  onPaste,
  setFromIso,
} = useDateSegments(toRef(props, 'modelValue'), (value) => emit('update:modelValue', value))

const shownError = computed(
  () => props.error || (incomplete.value ? t('components.datePicker.incomplete') : ''),
)

function onGroupFocusout(event) {
  if (event.currentTarget.contains(event.relatedTarget)) return
  touched.value = true
  emit('blur', event)
}

function openCalendar() {
  if (typeof native.value?.showPicker === 'function') native.value.showPicker()
  else native.value?.click()
}
</script>

<template>
  <FormField
    v-slot="{ id, describedBy, invalid }"
    :label="label"
    :name="name"
    :error="shownError"
    :hint="hint"
    :required="required"
    :disabled="disabled"
    filled
  >
    <div
      class="field__control datepicker"
      role="group"
      :aria-label="label"
      :aria-describedby="describedBy"
      @focusout="onGroupFocusout"
      @paste="onPaste"
    >
      <template v-for="(segment, index) in DATE_SEGMENTS" :key="segment.key">
        <span v-if="index > 0" class="datepicker__separator" aria-hidden="true">/</span>
        <input
          :id="index === 0 ? id : undefined"
          :ref="bindInput(segment.key)"
          :class="['datepicker__segment', `datepicker__segment--${segment.key}`]"
          type="text"
          inputmode="numeric"
          autocomplete="off"
          dir="ltr"
          :value="segments[segment.key]"
          :placeholder="t(`components.datePicker.${segment.key}`)"
          :aria-label="t(`components.datePicker.${segment.key}Label`, { label })"
          :aria-invalid="invalid || undefined"
          :required="required"
          :disabled="disabled"
          @input="onSegmentInput(index, $event)"
          @keydown="onSegmentKeydown(index, $event)"
        />
      </template>
      <button
        type="button"
        class="datepicker__calendar"
        :aria-label="t('components.datePicker.openCalendar', { label })"
        :title="t('components.datePicker.openCalendar', { label })"
        :disabled="disabled"
        @click="openCalendar"
      >
        <AppIcon name="calendar" />
      </button>
      <input
        ref="native"
        class="datepicker__native"
        type="date"
        tabindex="-1"
        aria-hidden="true"
        :name="name"
        :value="modelValue"
        :min="min || undefined"
        :max="max || undefined"
        :disabled="disabled"
        @input="setFromIso($event.target.value)"
      />
    </div>
  </FormField>
</template>
