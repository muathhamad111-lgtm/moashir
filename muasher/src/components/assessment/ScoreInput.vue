<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { SCALE_KINDS } from '@/constants/assessment'
import { valueToRatio, ratioToValue, ratioToPercent, scaleMax } from '@/utils/assessmentScoring'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Number, default: 0 },
  scaleKind: {
    type: String,
    default: SCALE_KINDS.OF_10,
    validator: (value) => Object.values(SCALE_KINDS).includes(value),
  },
  label: { type: String, required: true },
  describedBy: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const inputId = useId()

const max = computed(() => scaleMax(props.scaleKind))
const step = computed(() => (props.scaleKind === SCALE_KINDS.RELATIVE ? 5 : 1))
const isRelative = computed(() => props.scaleKind === SCALE_KINDS.RELATIVE)

const rawValue = computed(() => {
  const value = ratioToValue(props.modelValue, props.scaleKind)
  return Math.round(value * 100) / 100
})

const percent = computed(() => ratioToPercent(props.modelValue))

function commitValue(value) {
  const bounded = Math.min(Math.max(Number(value) || 0, 0), max.value)
  emit('update:modelValue', valueToRatio(bounded, props.scaleKind))
}

function onInput(event) {
  commitValue(event.target.value)
}

function decrement() {
  if (props.disabled) return
  commitValue(rawValue.value - step.value)
}

function increment() {
  if (props.disabled) return
  commitValue(rawValue.value + step.value)
}

const atMin = computed(() => props.modelValue <= 0)
const atMax = computed(() => props.modelValue >= 1)
</script>

<template>
  <div class="score-input" :class="{ 'is-disabled': disabled }">
    <label :for="inputId" class="score-input__label">{{ label }}</label>
    <div class="score-input__control">
      <button
        type="button"
        class="score-input__step"
        :disabled="disabled || atMin"
        :aria-label="t('assessment.score.decrease')"
        @click="decrement"
      >
        <AppIcon name="minus" />
      </button>
      <div class="score-input__field">
        <input
          :id="inputId"
          class="score-input__number"
          type="number"
          inputmode="decimal"
          :min="0"
          :max="max"
          :step="step"
          :value="rawValue"
          :disabled="disabled"
          :aria-describedby="describedBy || undefined"
          :aria-valuemin="0"
          :aria-valuemax="max"
          :aria-valuenow="rawValue"
          @input="onInput"
        />
        <span class="score-input__unit" aria-hidden="true">
          {{
            isRelative ? t('assessment.score.percentUnit') : t('assessment.score.ofTen', { max })
          }}
        </span>
      </div>
      <button
        type="button"
        class="score-input__step"
        :disabled="disabled || atMax"
        :aria-label="t('assessment.score.increase')"
        @click="increment"
      >
        <AppIcon name="plus" />
      </button>
    </div>
    <p class="score-input__percent">
      <AppIcon name="percent" class="score-input__percent-icon" />
      <span>{{ t('assessment.score.reflected', { percent }) }}</span>
    </p>
  </div>
</template>
