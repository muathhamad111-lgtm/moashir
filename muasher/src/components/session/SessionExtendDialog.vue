<script setup>
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { MODAL_ROLES, MODAL_TONES } from '@/constants/modal'

defineProps({
  open: { type: Boolean, required: true },
  countdown: { type: String, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['extend', 'sign-out'])
const { t } = useI18n()
const messageId = useId()
</script>

<template>
  <BaseModal
    :model-value="open"
    size="sm"
    :role="MODAL_ROLES.ALERT_DIALOG"
    :title="t('auth.session.extendTitle')"
    icon="clock"
    :tone="MODAL_TONES.WARNING"
    :described-by="messageId"
    :close-on-backdrop="false"
    @update:model-value="emit('extend')"
  >
    <p :id="messageId" class="modal__message">{{ t('auth.session.extendMessage') }}</p>
    <p class="session-extend__countdown" role="timer">
      <span>{{ t('auth.session.timeLeft') }}</span>
      <bdi class="session-extend__time">{{ countdown }}</bdi>
    </p>
    <template #footer>
      <BaseButton variant="primary" :loading="busy" data-autofocus @click="emit('extend')">
        {{ t('auth.session.extendAction') }}
      </BaseButton>
      <BaseButton variant="secondary" :disabled="busy" @click="emit('sign-out')">
        {{ t('auth.session.saveAndSignOut') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
