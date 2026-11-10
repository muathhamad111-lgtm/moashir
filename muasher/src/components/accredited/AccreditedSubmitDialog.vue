<script setup>
import { useI18n } from 'vue-i18n'
import DecisionDialog from '@/components/modal/DecisionDialog.vue'

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
  <DecisionDialog
    :model-value="modelValue"
    :title="resubmission ? t('accredited.submit.resubmitTitle') : t('accredited.submit.title')"
    :message="number"
    :confirm-label="t('actions.send')"
    icon="send"
    :busy="submitting"
    @update:model-value="emit('update:modelValue', $event)"
    @confirm="emit('confirm')"
  />
</template>
