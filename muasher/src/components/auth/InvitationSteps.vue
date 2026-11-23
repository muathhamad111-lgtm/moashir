<script setup>
import { useI18n } from 'vue-i18n'

const STEPS = ['password', 'nda', 'mfa']

defineProps({
  current: { type: String, required: true },
})

const { t } = useI18n()
</script>

<template>
  <ol class="nda-steps" :aria-label="t('nda.invitation.stepsLabel')">
    <li
      v-for="(step, index) in STEPS"
      :key="step"
      class="nda-steps__item"
      :aria-current="step === current ? 'step' : undefined"
    >
      {{ t('nda.invitation.step', { number: index + 1, name: t(`nda.invitation.steps.${step}`) }) }}
    </li>
  </ol>
</template>
