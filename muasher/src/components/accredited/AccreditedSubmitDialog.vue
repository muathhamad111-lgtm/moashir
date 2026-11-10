<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DecisionDialog from '@/components/modal/DecisionDialog.vue'
import { isolate } from '@/utils/bidi'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  number: { type: String, required: true },
  period: { type: String, default: '' },
  blockedUntil: { type: String, default: '' },
  resubmission: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()

const isBlocked = computed(() => props.blockedUntil !== '')
const title = computed(() => {
  if (isBlocked.value)
    return t('accredited.submit.blockedTitle', { date: isolate(props.blockedUntil) })
  return props.resubmission ? t('accredited.submit.resubmitTitle') : t('accredited.submit.title')
})
const message = computed(() => {
  if (isBlocked.value) return t('accredited.submit.blockedMessage')
  return props.period ? t('accredited.submit.periodLine', { period: props.period }) : props.number
})

function onConfirm() {
  if (isBlocked.value) emit('update:modelValue', false)
  else emit('confirm')
}
</script>

<template>
  <DecisionDialog
    :model-value="modelValue"
    :title="title"
    :message="message"
    :confirm-label="isBlocked ? t('actions.ok') : t('actions.send')"
    :variant="isBlocked ? 'warning' : 'confirm'"
    :icon="isBlocked ? 'calendar' : 'send'"
    :busy="submitting"
    @update:model-value="emit('update:modelValue', $event)"
    @confirm="onConfirm"
  />
</template>
