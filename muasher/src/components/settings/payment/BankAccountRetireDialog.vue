<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BankAccountPayee from './BankAccountPayee.vue'
import { RETIRE_ACTIONS } from '@/composables/useBankAccountsSettings'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  account: { type: Object, default: null },
  mode: { type: String, default: RETIRE_ACTIONS.DEACTIVATE },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const busy = ref(false)

const variant = computed(() => {
  if (props.mode === RETIRE_ACTIONS.DEACTIVATE) return 'deactivate'
  return props.account?.isUsed ? 'archive' : 'delete'
})

function close() {
  emit('update:modelValue', false)
}

async function confirm() {
  if (busy.value) return
  busy.value = true
  try {
    await props.action(props.account)
    close()
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="sm"
    :title="t(`settings.bankAccounts.retire.${variant}.title`)"
    :close-on-backdrop="!busy"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-if="account" class="bank-account-retire">
      <BankAccountPayee :account="account" />
      <p class="bank-account-retire__message">
        {{ t(`settings.bankAccounts.retire.${variant}.message`) }}
      </p>
    </div>
    <template #footer>
      <BaseButton variant="secondary" :disabled="busy" @click="close">
        {{ t('actions.cancel') }}
      </BaseButton>
      <BaseButton variant="danger" :loading="busy" @click="confirm">
        {{ t(`settings.bankAccounts.retire.${variant}.confirm`) }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
