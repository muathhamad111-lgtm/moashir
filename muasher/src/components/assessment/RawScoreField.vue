<script setup>
import { computed, toRef } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import { isFilled, sameRawValue } from '@/utils/assessments/scoreSheet'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  maximum: { type: String, required: true },
  saved: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
})

const { t } = useI18n()
const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))

const isSaved = computed(
  () => Boolean(props.saved) && sameRawValue(value.value, props.saved.rawValue),
)

const hint = computed(() => {
  if (isSaved.value) {
    return t('assessment.self.score.saved', {
      raw: props.saved.rawValue,
      max: props.maximum,
      percent: props.saved.percent,
    })
  }
  if (isFilled(value.value)) return t('assessment.self.score.unsaved', { max: props.maximum })
  return t('assessment.self.score.empty', { max: props.maximum })
})
</script>

<template>
  <div class="raw-score" :class="{ 'raw-score--saved': isSaved }">
    <BaseInput
      v-model="value"
      :name="name"
      :label="label"
      :hint="hint"
      :error="errorMessage"
      :disabled="disabled"
      inputmode="decimal"
      autocomplete="off"
      input-dir="ltr"
      @blur="handleBlur"
    />
  </div>
</template>
