<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import CriterionStepButtons from '@/components/assessment/CriterionStepButtons.vue'

defineProps({
  saving: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  dirty: { type: Boolean, default: false },
  resubmission: { type: Boolean, default: false },
  submitBlocked: { type: Boolean, default: false },
  remaining: { type: Number, default: 0 },
  isFirst: { type: Boolean, default: true },
  isLast: { type: Boolean, default: true },
})

const emit = defineEmits(['save', 'submit', 'previous', 'next'])
const { t } = useI18n()
</script>

<template>
  <div class="accredited-actions" role="group" :aria-label="t('accredited.form.actionsLabel')">
    <p class="accredited-actions__status" aria-live="polite">
      {{ dirty ? t('accredited.form.unsaved') : t('accredited.form.allSaved') }}
      <span v-if="remaining > 0" class="accredited-actions__remaining">
        {{ t('portal.assessments.task.remainingHint', { count: remaining }, remaining) }}
      </span>
      <span v-if="isLast" class="accredited-actions__hint">
        <AppIcon name="lock" />
        {{ t('accredited.submit.lockNote') }}
      </span>
    </p>
    <CriterionStepButtons
      class="accredited-actions__buttons"
      :is-first="isFirst"
      :is-last="isLast"
      :disabled="saving || submitting"
      @previous="emit('previous')"
      @next="emit('next')"
    >
      <slot name="extra" />
      <BaseButton
        variant="secondary"
        icon="save"
        data-testid="accredited-save"
        :loading="saving"
        :disabled="!dirty || submitting"
        @click="emit('save')"
      >
        {{ t('components.steps.saveDraft') }}
      </BaseButton>
      <template #last>
        <BaseButton
          variant="primary"
          icon="send"
          data-testid="accredited-submit"
          :loading="submitting"
          :disabled="saving || submitBlocked"
          @click="emit('submit')"
        >
          {{ resubmission ? t('accredited.form.resubmit') : t('accredited.form.submit') }}
        </BaseButton>
      </template>
    </CriterionStepButtons>
  </div>
</template>
