<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  steps: { type: Array, required: true },
  currentIndex: { type: Number, required: true },
  clickable: { type: Boolean, default: true },
})

const emit = defineEmits(['select'])
const { t } = useI18n()

function stepState(index) {
  const step = props.steps[index]
  return {
    'is-active': index === props.currentIndex,
    'is-complete': index < props.currentIndex,
    'is-invalid': step.valid === false,
  }
}

function select(index) {
  if (!props.clickable || index === props.currentIndex) return
  emit('select', index)
}
</script>

<template>
  <ol class="wizard" :aria-label="t('components.wizard.label')">
    <li
      v-for="(step, index) in steps"
      :key="step.id"
      class="wizard__step"
      :class="stepState(index)"
    >
      <button
        type="button"
        class="wizard__button"
        :aria-current="index === currentIndex ? 'step' : undefined"
        :disabled="!clickable || undefined"
        @click="select(index)"
      >
        <span class="wizard__marker" aria-hidden="true">
          <AppIcon v-if="index < currentIndex" name="check" />
          <AppIcon v-else-if="step.valid === false" name="warning-triangle" />
          <template v-else>{{ index + 1 }}</template>
        </span>
        <span class="wizard__label">{{ step.label }}</span>
      </button>
    </li>
  </ol>
</template>
