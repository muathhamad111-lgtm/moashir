<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DecisionDialog from '@/components/modal/DecisionDialog.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  reference: { type: String, default: '' },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const busy = ref(false)

async function confirm() {
  if (busy.value) return
  busy.value = true
  try {
    const done = await props.action()
    if (done) emit('update:modelValue', false)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <DecisionDialog
    :model-value="modelValue"
    :title="t('portal.checkoutMethod.change.title')"
    :message="t('portal.checkoutMethod.change.cancels', { reference })"
    :confirm-label="t('portal.checkoutMethod.change.confirm')"
    variant="warning"
    :busy="busy"
    @update:model-value="emit('update:modelValue', $event)"
    @confirm="confirm"
  />
</template>
