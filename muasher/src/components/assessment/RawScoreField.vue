<script setup>
import { computed, toRef } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import ScoreStepper from '@/components/assessment/ScoreStepper.vue'
import { isFilled, sameRawValue } from '@/utils/assessments/scoreSheet'
import { sanitizeScoreText, stepScore } from '@/utils/assessments/scoreStep'
import { compactDecimal, twoDecimals } from '@/utils/percentText'

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

const STEP_KEYS = Object.freeze({ ArrowUp: 1, ArrowDown: -1 })

const max = computed(() => compactDecimal(props.maximum))

function step(delta) {
  if (props.disabled) return
  value.value = stepScore(value.value, delta, props.maximum)
}

function onKeydown(event) {
  const delta = STEP_KEYS[event.key]
  if (!delta || event.target.tagName !== 'INPUT') return
  event.preventDefault()
  step(delta)
}

const hint = computed(() => {
  if (isSaved.value) {
    return t('assessment.self.score.saved', {
      raw: compactDecimal(props.saved.rawValue),
      max: max.value,
      percent: twoDecimals(props.saved.percent),
    })
  }
  if (isFilled(value.value)) return t('assessment.self.score.unsaved', { max: max.value })
  return t('assessment.self.score.empty', { max: max.value })
})
</script>

<template>
  <div class="raw-score" :class="{ 'raw-score--saved': isSaved }" @keydown="onKeydown">
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
      :filter="sanitizeScoreText"
      @blur="handleBlur"
    >
      <template #addon="{ id }">
        <ScoreStepper
          :value="value"
          :maximum="maximum"
          :label="label"
          :controls="id"
          :disabled="disabled"
          @step="step"
        />
      </template>
    </BaseInput>
  </div>
</template>
