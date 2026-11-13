<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from './BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { DECISION_ICONS, DECISION_VARIANTS, MODAL_ROLES } from '@/constants/modal'
import { buttonVariantOf, decisionVariantOf, focusesSafeAction } from '@/utils/decisionVariant'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: '' },
  cancelLabel: { type: String, default: '' },
  variant: {
    type: String,
    default: DECISION_VARIANTS.CONFIRM,
    validator: (value) => Object.values(DECISION_VARIANTS).includes(value),
  },
  icon: { type: String, default: '' },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()
const messageId = useId()

const tone = computed(() => decisionVariantOf(props.variant))
const guardsSafeAction = computed(() => focusesSafeAction(tone.value))

function dismiss() {
  if (!props.busy) emit('update:modelValue', false)
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="sm"
    :role="MODAL_ROLES.ALERT_DIALOG"
    :title="title"
    :icon="icon || DECISION_ICONS[tone]"
    :tone="tone"
    :described-by="message ? messageId : ''"
    :close-on-backdrop="!busy"
    @update:model-value="dismiss"
  >
    <template v-if="message || $slots.default" #default>
      <p v-if="message" :id="messageId" class="modal__message">
        <bdi>{{ message }}</bdi>
      </p>
      <slot />
    </template>
    <template #footer>
      <BaseButton
        :variant="buttonVariantOf(tone)"
        :loading="busy"
        :data-autofocus="guardsSafeAction ? undefined : ''"
        @click="emit('confirm')"
      >
        {{ confirmLabel || t('actions.confirm') }}
      </BaseButton>
      <BaseButton
        variant="secondary"
        :disabled="busy"
        :data-autofocus="guardsSafeAction ? '' : undefined"
        @click="dismiss"
      >
        {{ cancelLabel || t('actions.cancel') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
