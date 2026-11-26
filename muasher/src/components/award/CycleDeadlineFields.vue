<script setup>
import { useI18n } from 'vue-i18n'
import CycleStepCounter from './CycleStepCounter.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  locked: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <fieldset class="cycle-form__section">
    <legend class="cycle-form__legend">
      {{ t('cycles.form.sections.deadlines') }}
      <CycleStepCounter />
    </legend>
    <div class="cycle-form__grid">
      <FieldInput
        name="initialReviewSlaDays"
        :label="t('cycles.form.initialReviewSlaDays')"
        inputmode="numeric"
        input-dir="ltr"
        :hint="t('cycles.form.initialReviewSlaDaysHint')"
        required
        :disabled="locked.has('initialReviewSlaDays')"
      />
    </div>
    <p class="cycle-form__note" role="note">
      <AppIcon name="info-circle" />
      {{ t('cycles.setup.deadlines.later') }}
    </p>
  </fieldset>
</template>
