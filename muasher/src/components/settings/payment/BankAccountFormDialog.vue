<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import FieldIban from '@/components/form/FieldIban.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { bankAccountPayload, bankAccountSchema } from '@/utils/payments/bankTransferSchemas'
import { BANK_ACCOUNT_RULES } from '@/constants/bankTransfer'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

function emptyValues() {
  return {
    bankName: { ar: '', en: '' },
    accountHolderName: { ar: '', en: '' },
    iban: '',
    accountNumber: '',
    payerNote: { ar: '', en: '' },
  }
}

const form = useValidatedForm({
  schema: bankAccountSchema(t),
  initialValues: emptyValues(),
  action: 'bankAccounts.create',
  submit: async (values) => {
    const created = await props.action(bankAccountPayload(values))
    if (created) emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: emptyValues() })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('settings.bankAccounts.form.title')"
    :busy="form.submitting.value"
    :submit-label="t('settings.bankAccounts.form.submit')"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="bank-account-form">
      <p class="bank-account-form__lead">{{ t('settings.bankAccounts.form.lead') }}</p>
      <p class="bank-account-form__notice" role="note">
        <AppIcon name="bell-dot" />
        {{ t('settings.bankAccounts.form.notice') }}
      </p>
      <div class="bank-account-form__grid">
        <FieldInput
          name="bankName.ar"
          :label="t('settings.bankAccounts.fields.bankNameAr')"
          required
        />
        <FieldInput
          name="bankName.en"
          :label="t('settings.bankAccounts.fields.bankNameEn')"
          input-dir="ltr"
          required
        />
        <FieldInput
          name="accountHolderName.ar"
          :label="t('settings.bankAccounts.fields.holderAr')"
          required
        />
        <FieldInput
          name="accountHolderName.en"
          :label="t('settings.bankAccounts.fields.holderEn')"
          input-dir="ltr"
          required
        />
      </div>
      <FieldIban
        name="iban"
        :label="t('settings.bankAccounts.fields.iban')"
        :hint="t('settings.bankAccounts.iban.hint')"
        required
      />
      <FieldInput
        name="accountNumber"
        :label="t('settings.bankAccounts.fields.accountNumber')"
        :hint="
          t('settings.bankAccounts.fields.accountNumberHint', {
            min: BANK_ACCOUNT_RULES.ACCOUNT_NUMBER_MIN,
            max: BANK_ACCOUNT_RULES.ACCOUNT_NUMBER_MAX,
          })
        "
        input-dir="ltr"
      />
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
      <p class="bank-account-form__footnote">{{ t('settings.bankAccounts.form.footnote') }}</p>
    </div>
  </FormModal>
</template>
