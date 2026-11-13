<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  saving: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  dirty: { type: Boolean, default: false },
  resubmission: { type: Boolean, default: false },
  submitBlocked: { type: Boolean, default: false },
  remaining: { type: Number, default: 0 },
})

const emit = defineEmits(['save', 'submit'])
const { t } = useI18n()
</script>

<template>
  <div class="accredited-actions" role="group" :aria-label="t('accredited.form.actionsLabel')">
    <p class="accredited-actions__status" aria-live="polite">
      {{ dirty ? t('accredited.form.unsaved') : t('accredited.form.allSaved') }}
      <span v-if="remaining > 0" class="accredited-actions__remaining">
        {{ t('portal.assessments.task.remainingHint', { count: remaining }, remaining) }}
      </span>
      <span class="accredited-actions__hint">
        <AppIcon name="lock" />
        {{ t('accredited.submit.lockNote') }}
      </span>
    </p>
    <div class="accredited-actions__buttons">
      <slot name="extra" />
      <BaseButton
        variant="secondary"
        icon="save"
        :loading="saving"
        :disabled="!dirty || submitting"
        @click="emit('save')"
      >
        {{ t('accredited.form.saveCv') }}
      </BaseButton>
      <BaseButton
        variant="primary"
        icon="send"
        :loading="submitting"
        :disabled="saving || submitBlocked"
        @click="emit('submit')"
      >
        {{ resubmission ? t('accredited.form.resubmit') : t('accredited.form.submit') }}
      </BaseButton>
    </div>
  </div>
</template>
