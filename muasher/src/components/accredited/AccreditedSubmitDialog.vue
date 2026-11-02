<script setup>
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  modelValue: { type: Boolean, required: true },
  number: { type: String, required: true },
  resubmission: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="md"
    :title="resubmission ? t('accredited.submit.resubmitTitle') : t('accredited.submit.title')"
    :close-on-backdrop="!submitting"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="accredited-submit">
      <p class="accredited-submit__lead">{{ t(resubmission ? 'accredited.submit.resubmitMessage' : 'accredited.submit.message', { number }) }}</p>
      <ul class="accredited-submit__facts">
        <li class="accredited-submit__fact">
          <i class="fa-solid fa-lock" aria-hidden="true"></i>
          {{ t('accredited.submit.lockNote') }}
        </li>
        <li class="accredited-submit__fact">
          <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
          {{ t('accredited.submit.reviewNote') }}
        </li>
      </ul>
    </div>
    <template #footer>
      <BaseButton variant="secondary" :disabled="submitting" @click="emit('update:modelValue', false)">{{ t('actions.cancel') }}</BaseButton>
      <BaseButton variant="primary" icon="fa-solid fa-paper-plane" :loading="submitting" @click="emit('confirm')">
        {{ resubmission ? t('accredited.form.resubmit') : t('accredited.form.submit') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
