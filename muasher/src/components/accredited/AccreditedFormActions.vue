<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  saving: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  dirty: { type: Boolean, default: false },
  resubmission: { type: Boolean, default: false },
  submitBlocked: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'submit'])
const { t } = useI18n()
</script>

<template>
  <div class="accredited-actions" role="group" :aria-label="t('accredited.form.actionsLabel')">
    <p class="accredited-actions__status" aria-live="polite">
      {{ dirty ? t('accredited.form.unsaved') : t('accredited.form.allSaved') }}
    </p>
    <div class="accredited-actions__buttons">
      <slot name="extra" />
      <BaseButton variant="secondary" icon="fa-regular fa-floppy-disk" :loading="saving" :disabled="!dirty || submitting" @click="emit('save')">
        {{ t('accredited.form.saveCv') }}
      </BaseButton>
      <BaseButton variant="primary" icon="fa-solid fa-paper-plane" :loading="submitting" :disabled="saving || submitBlocked" @click="emit('submit')">
        {{ resubmission ? t('accredited.form.resubmit') : t('accredited.form.submit') }}
      </BaseButton>
    </div>
  </div>
</template>
