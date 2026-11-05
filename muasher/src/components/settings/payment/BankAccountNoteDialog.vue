<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BankAccountPayee from './BankAccountPayee.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { payerNotePayload, payerNoteSchema } from '@/utils/payments/bankTransferSchemas'
import { BANK_ACCOUNT_RULES } from '@/constants/bankTransfer'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  account: { type: Object, default: null },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

function valuesOf(account) {
  return { payerNote: { ar: account?.payerNote?.ar ?? '', en: account?.payerNote?.en ?? '' } }
}

const form = useValidatedForm({
  schema: payerNoteSchema(t),
  initialValues: valuesOf(props.account),
  action: 'bankAccounts.updateNote',
  submit: async (values) => {
    const saved = await props.action(props.account, payerNotePayload(values))
    if (saved) emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: valuesOf(props.account) })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('settings.bankAccounts.note.title')"
    :busy="form.submitting.value"
    :submit-label="t('actions.save')"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div v-if="account" class="bank-account-form">
      <BankAccountPayee :account="account" />
      <p class="bank-account-form__notice" role="note">
        <AppIcon name="lock" />
        {{ t('settings.bankAccounts.note.immutable') }}
      </p>
      <div class="bank-account-form__grid">
        <FieldTextarea
          name="payerNote.ar"
          :label="t('settings.bankAccounts.fields.noteAr')"
          :hint="t('settings.bankAccounts.fields.noteHint', { max: BANK_ACCOUNT_RULES.NOTE_MAX })"
        />
        <FieldTextarea
          name="payerNote.en"
          :label="t('settings.bankAccounts.fields.noteEn')"
          :hint="t('settings.bankAccounts.fields.noteHint', { max: BANK_ACCOUNT_RULES.NOTE_MAX })"
        />
      </div>
      <p class="bank-account-form__footnote">{{ t('settings.bankAccounts.note.orderHint') }}</p>
    </div>
  </FormModal>
</template>
