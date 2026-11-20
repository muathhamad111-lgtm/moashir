<script setup>
import { computed, toRef, useId } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { isFilled, normalizedRawValue, sameRawValue } from '@/utils/assessments/scoreSheet'
import { toHundredths } from '@/utils/weight'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  saved: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
})

const ACHIEVED = '1'
const NOT_ACHIEVED = '0'
const OPTIONS = Object.freeze([
  { value: ACHIEVED, key: 'achieved' },
  { value: NOT_ACHIEVED, key: 'notAchieved' },
])

const { t } = useI18n()
const { value, errorMessage } = useField(toRef(props, 'name'))
const baseId = useId()

function stateOf(raw) {
  if (!isFilled(raw)) return null
  return toHundredths(normalizedRawValue(raw)) > 0 ? ACHIEVED : NOT_ACHIEVED
}

const chosen = computed(() => stateOf(value.value))
const isSaved = computed(
  () => Boolean(props.saved) && sameRawValue(value.value, props.saved.rawValue),
)
const hint = computed(() => {
  if (isSaved.value) {
    const key = stateOf(props.saved.rawValue) === ACHIEVED ? 'achieved' : 'notAchieved'
    return t('assessment.self.achievement.saved', {
      state: t(`assessment.self.achievement.${key}`),
    })
  }
  return t(`assessment.self.achievement.${chosen.value ? 'unsaved' : 'empty'}`)
})
</script>

<template>
  <fieldset
    class="achievement-score raw-score"
    :class="{ 'raw-score--saved': isSaved }"
    :disabled="disabled"
    :aria-describedby="`${baseId}-hint`"
  >
    <legend class="field__label">{{ label }}</legend>
    <div class="achievement-score__options">
      <label
        v-for="option in OPTIONS"
        :key="option.value"
        class="radio"
        :for="`${baseId}-${option.value}`"
      >
        <input
          :id="`${baseId}-${option.value}`"
          class="radio__input"
          type="radio"
          :name="name"
          :value="option.value"
          :checked="chosen === option.value"
          @change="value = option.value"
        />
        <span class="radio__label">{{ t(`assessment.self.achievement.${option.key}`) }}</span>
      </label>
    </div>
    <p :id="`${baseId}-hint`" class="achievement-score__hint">{{ hint }}</p>
    <p v-if="errorMessage" class="field__error" role="alert">{{ errorMessage }}</p>
  </fieldset>
</template>
