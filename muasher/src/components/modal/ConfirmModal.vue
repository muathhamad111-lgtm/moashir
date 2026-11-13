<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DecisionDialog from './DecisionDialog.vue'
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
  <DecisionDialog
    v-model="isOpen"
    :title="confirmState.title || t('components.confirm.title')"
    :message="confirmState.message"
    :confirm-label="confirmState.confirmLabel"
    :cancel-label="confirmState.cancelLabel"
    :variant="confirmState.variant"
    :icon="confirmState.icon"
    @confirm="accept"
  />
</template>
