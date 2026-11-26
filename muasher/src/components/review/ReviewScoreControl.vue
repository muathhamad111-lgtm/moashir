<script setup>
import { computed, toRef, useId } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import ReviewAchievementControl from './ReviewAchievementControl.vue'
import { isFilled, sameRawValue } from '@/utils/assessments/scoreSheet'
import {
  canDecreaseScore,
  canIncreaseScore,
  sanitizeScoreText,
  stepScore,
} from '@/utils/assessments/scoreStep'
import { isAchievement, maximumOf } from '@/utils/assessments/scoringTypes'
import { compactDecimal, twoDecimals } from '@/utils/percentText'
import { SCORING_TYPES } from '@/constants/criteria'

// The score of one item, facing its title (design §B.3.1): − [ value / max ] + and what it is worth.
// The field keeps the score sheet's name, so validation and saving are unchanged.
const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  scoringType: { type: String, required: true },
  saved: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
  // Replaces «غير محفوظ» for a changed value, e.g. the published value in the override mode.
  changedLabel: { type: String, default: '' },
})

// «نسبي» moves in steps of 5; a typed value can still be anything on the scale.
const RELATIVE_STEP = 5

const { t } = useI18n()
const id = useId()
const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))

const maximum = computed(() => maximumOf(props.scoringType))
const isRelative = computed(() => props.scoringType === SCORING_TYPES.RELATIVE)
const delta = computed(() => (isRelative.value ? RELATIVE_STEP : 1))
// The percent is the server's, shown once the value is saved; an unsaved value is never converted
// on the client (the same rule as the entity's score fields).
const isSaved = computed(
  () => Boolean(props.saved) && sameRawValue(value.value, props.saved.rawValue),
)
const isUnsaved = computed(() => isFilled(value.value) && !isSaved.value)

function step(direction) {
  if (props.disabled) return
  value.value = stepScore(value.value, direction * delta.value, maximum.value)
}

function onInput(event) {
  value.value = sanitizeScoreText(event.target.value)
}

const STEP_KEYS = Object.freeze({ ArrowUp: 1, ArrowDown: -1 })

function onKeydown(event) {
  const direction = STEP_KEYS[event.key]
  if (!direction) return
  event.preventDefault()
  step(direction)
}
</script>

<template>
  <ReviewAchievementControl
    v-if="isAchievement(scoringType)"
    :name="name"
    :label="label"
    :saved="saved"
    :disabled="disabled"
    :changed-label="changedLabel"
  />
  <div v-else class="review-score raw-score" :class="{ 'raw-score--saved': isSaved }">
    <span v-if="isSaved" class="review-score__percent">
      <bdi>{{ t('review.score.percent', { value: twoDecimals(saved.percent) }) }}</bdi>
    </span>
    <span v-else-if="isUnsaved" class="review-score__unsaved">{{
      changedLabel || t('review.score.unsaved')
    }}</span>
    <div class="review-score__box" :class="{ 'has-error': errorMessage }">
      <button
        type="button"
        class="score-stepper__button review-score__step"
        :aria-label="t('assessment.score.decrease', { label })"
        :aria-controls="id"
        :disabled="disabled || !canDecreaseScore(value)"
        @click="step(-1)"
      >
        <AppIcon name="minus" />
      </button>
      <label class="review-score__label" :for="id">{{ label }}</label>
      <input
        :id="id"
        class="review-score__input"
        :name="name"
        :value="value"
        :disabled="disabled"
        inputmode="decimal"
        autocomplete="off"
        dir="ltr"
        :aria-invalid="errorMessage ? 'true' : undefined"
        :aria-describedby="errorMessage ? `${id}-error` : undefined"
        @input="onInput"
        @blur="handleBlur"
        @keydown="onKeydown"
      />
      <span class="review-score__max" aria-hidden="true">
        <bdi>{{ isRelative ? '%' : `/ ${compactDecimal(maximum)}` }}</bdi>
      </span>
      <button
        type="button"
        class="score-stepper__button review-score__step"
        :aria-label="t('assessment.score.increase', { label })"
        :aria-controls="id"
        :disabled="disabled || !canIncreaseScore(value, maximum)"
        @click="step(1)"
      >
        <AppIcon name="plus" />
      </button>
    </div>
    <p
      v-if="errorMessage"
      :id="`${id}-error`"
      class="field__error review-score__error"
      role="alert"
    >
      {{ errorMessage }}
    </p>
  </div>
</template>
