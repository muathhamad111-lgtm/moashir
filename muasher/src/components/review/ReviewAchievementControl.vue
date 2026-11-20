<script setup>
import { computed, toRef, useId } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { isFilled, normalizedRawValue, sameRawValue } from '@/utils/assessments/scoreSheet'
import { toHundredths } from '@/utils/weight'

// «متحقق / غير متحقق» as two large buttons (design §B.3.1), not bare radios. They behave as a
// radio group: one choice, arrow keys move between them.
const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  saved: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
})

const ACHIEVED = '1'
const NOT_ACHIEVED = '0'
const OPTIONS = Object.freeze([
  { value: ACHIEVED, key: 'achieved', icon: 'check' },
  { value: NOT_ACHIEVED, key: 'notAchieved', icon: 'x' },
])

const { t } = useI18n()
const id = useId()
const { value, errorMessage } = useField(toRef(props, 'name'))

const chosen = computed(() => {
  if (!isFilled(value.value)) return null
  return toHundredths(normalizedRawValue(value.value)) > 0 ? ACHIEVED : NOT_ACHIEVED
})

const isUnsaved = computed(
  () => isFilled(value.value) && !(props.saved && sameRawValue(value.value, props.saved.rawValue)),
)

function choose(option) {
  if (!props.disabled) value.value = option
}

function onKeydown(event) {
  if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return
  event.preventDefault()
  choose(chosen.value === ACHIEVED ? NOT_ACHIEVED : ACHIEVED)
}
</script>

<template>
  <div class="review-achievement">
    <span v-if="isUnsaved" class="review-score__unsaved">{{ t('review.score.unsaved') }}</span>
    <div
      class="review-achievement__options"
      role="radiogroup"
      :aria-label="label"
      :aria-describedby="errorMessage ? `${id}-error` : undefined"
      @keydown="onKeydown"
    >
      <button
        v-for="option in OPTIONS"
        :key="option.value"
        type="button"
        role="radio"
        class="review-achievement__option"
        :class="`review-achievement__option--${option.key}`"
        :data-score-name="name"
        :aria-checked="chosen === option.value"
        :tabindex="chosen === option.value || (!chosen && option.value === ACHIEVED) ? 0 : -1"
        :disabled="disabled"
        @click="choose(option.value)"
      >
        <AppIcon :name="option.icon" />
        {{ t(`assessment.self.achievement.${option.key}`) }}
      </button>
    </div>
    <p v-if="errorMessage" :id="`${id}-error`" class="field__error" role="alert">
      {{ errorMessage }}
    </p>
  </div>
</template>
