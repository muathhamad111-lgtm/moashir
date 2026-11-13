<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DecisionDialog from '@/components/modal/DecisionDialog.vue'
import { RETIRE_ACTIONS } from '@/composables/useBankAccountsSettings'
import { localizedText } from '@/utils/localizedText'
import { ibanLast4 } from '@/utils/payments/iban'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  account: { type: Object, default: null },
  mode: { type: String, default: RETIRE_ACTIONS.DEACTIVATE },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()
const busy = ref(false)

const deactivating = computed(() => props.mode === RETIRE_ACTIONS.DEACTIVATE)
const accountLine = computed(() =>
  props.account
    ? t('settings.bankAccounts.blocked.subtitle', {
        bank: localizedText(props.account.bankName, locale.value),
        last4: ibanLast4(props.account.iban),
      })
    : '',
)

async function confirm() {
  if (busy.value) return
  busy.value = true
  try {
    await props.action(props.account)
    emit('update:modelValue', false)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <DecisionDialog
    :model-value="modelValue"
    :title="
      t(`settings.bankAccounts.retire.${deactivating ? 'deactivate' : 'delete'}.title`)
    "
    :message="accountLine"
    :confirm-label="
      deactivating ? t('settings.bankAccounts.retire.deactivate.confirm') : t('actions.delete')
    "
    :variant="deactivating ? 'warning' : 'danger'"
    :icon="deactivating ? '' : 'trash'"
    :busy="busy"
    @update:model-value="emit('update:modelValue', $event)"
    @confirm="confirm"
  />
</template>
