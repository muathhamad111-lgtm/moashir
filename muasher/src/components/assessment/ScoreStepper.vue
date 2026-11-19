<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { canDecreaseScore, canIncreaseScore } from '@/utils/assessments/scoreStep'

const props = defineProps({
  value: { type: [String, Number], default: '' },
  maximum: { type: String, required: true },
  label: { type: String, required: true },
  controls: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['step'])

const { t } = useI18n()
const canDecrease = computed(() => !props.disabled && canDecreaseScore(props.value))
const canIncrease = computed(() => !props.disabled && canIncreaseScore(props.value, props.maximum))
</script>

<template>
  <div class="score-stepper">
    <button
      type="button"
      class="score-stepper__button"
      :aria-label="t('assessment.score.decrease', { label })"
      :aria-controls="controls"
      :disabled="!canDecrease"
      @click="emit('step', -1)"
    >
      <AppIcon name="minus" />
    </button>
    <button
      type="button"
      class="score-stepper__button"
      :aria-label="t('assessment.score.increase', { label })"
      :aria-controls="controls"
      :disabled="!canIncrease"
      @click="emit('step', 1)"
    >
      <AppIcon name="plus" />
    </button>
  </div>
</template>
