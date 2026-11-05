<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from './BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useConfirm } from '@/composables/useConfirm'

const { t } = useI18n()
const { confirmState, accept, dismiss } = useConfirm()

const isOpen = computed({
  get: () => confirmState.isOpen,
  set: (value) => {
    if (!value) dismiss()
  },
})
</script>

<template>
  <BaseModal
    v-model="isOpen"
    size="sm"
    :title="confirmState.title || t('components.confirm.title')"
  >
    <p>{{ confirmState.message }}</p>
    <template #footer>
      <BaseButton variant="secondary" @click="dismiss">
        {{ confirmState.cancelLabel || t('actions.cancel') }}
      </BaseButton>
      <BaseButton
        :variant="confirmState.variant === 'danger' ? 'danger' : 'primary'"
        @click="accept"
      >
        {{ confirmState.confirmLabel || t('actions.confirm') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
