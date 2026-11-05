<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  reference: { type: String, default: '' },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const busy = ref(false)

function close() {
  emit('update:modelValue', false)
}

async function confirm() {
  if (busy.value) return
  busy.value = true
  try {
    const done = await props.action()
    if (done) close()
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="sm"
    :title="t('portal.checkoutMethod.change.title')"
    :close-on-backdrop="!busy"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="change-method">
      <p>
        {{ t('portal.checkoutMethod.change.cancels') }}
        <bdi dir="ltr">{{ reference }}</bdi>
      </p>
      <p>{{ t('portal.checkoutMethod.change.then') }}</p>
    </div>
    <template #footer>
      <BaseButton variant="secondary" :disabled="busy" @click="close">
        {{ t('portal.checkoutMethod.change.keep') }}
      </BaseButton>
      <BaseButton variant="primary" icon="refresh" :loading="busy" @click="confirm">
        {{ t('portal.checkoutMethod.change.confirm') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
