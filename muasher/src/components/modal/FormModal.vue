<script setup>
import { useI18n } from 'vue-i18n'
import BaseModal from './BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, required: true },
  busy: { type: Boolean, default: false },
  submitLabel: { type: String, default: '' },
  submitVariant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
})

const emit = defineEmits(['update:modelValue', 'submit', 'close'])
const { t } = useI18n()
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="title"
    :size="size"
    :close-on-backdrop="!busy"
    @update:model-value="emit('update:modelValue', $event)"
    @close="emit('close')"
  >
    <form :aria-busy="busy || undefined" novalidate @submit.prevent="emit('submit')">
      <slot />
      <footer class="modal__footer">
        <BaseButton variant="secondary" :disabled="busy" @click="emit('update:modelValue', false)">
          {{ t('actions.cancel') }}
        </BaseButton>
        <BaseButton type="submit" :variant="submitVariant" :loading="busy">
          {{ submitLabel || t('actions.save') }}
        </BaseButton>
      </footer>
    </form>
  </BaseModal>
</template>
