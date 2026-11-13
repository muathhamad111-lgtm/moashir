<script setup>
import { toRef } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import RefundEvidenceField from './RefundEvidenceField.vue'
import { useRefundForm } from '@/composables/useRefundForm'
import { REFUND_RULES } from '@/constants/finance'
import { riyadhDate } from '@/utils/finance/refundSchema'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  transaction: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'recorded'])
const { t } = useI18n()

const refund = useRefundForm(toRef(props, 'transaction'), {
  onRecorded: (record) => {
    emit('update:modelValue', false)
    emit('recorded', record)
  },
})
const { total, paidOn, uploadState, refusal, submitting, selectEvidence, submit } = refund
const refundedOn = useField('refundedOn')
const evidence = useField('evidence')
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('finance.refund.formTitle')"
    :subtitle="transaction.reference"
    :busy="submitting"
    :submit-label="t('finance.refund.submit')"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <div class="refund-form">
      <p class="refund-form__total">
        {{ t('finance.refund.totalLabel') }}
        <MoneyAmount :value="total" :currency="transaction.pricing.currency" strong />
      </p>
      <div class="refund-form__grid">
        <FieldInput
          name="amount"
          :label="t('finance.refund.amount')"
          :hint="t('finance.refund.amountHint')"
          input-dir="ltr"
          disabled
          required
        />
        <BaseDatePicker
          v-model="refundedOn.value.value"
          name="refundedOn"
          :label="t('finance.refund.refundedOn')"
          :min="paidOn ?? ''"
          :max="riyadhDate()"
          :error="refundedOn.errorMessage.value"
          required
          @blur="refundedOn.handleBlur"
        />
        <FieldInput
          name="transferReference"
          :label="t('finance.refund.transferReference')"
          :hint="
            t('finance.refund.transferReferenceHint', {
              min: REFUND_RULES.TRANSFER_REFERENCE_MIN,
              max: REFUND_RULES.TRANSFER_REFERENCE_MAX,
            })
          "
          input-dir="ltr"
          required
        />
      </div>
      <FieldTextarea
        name="reason"
        :label="t('finance.refund.reason')"
        :hint="t('finance.refund.reasonHint', { min: REFUND_RULES.REASON_MIN })"
        :rows="4"
        required
      />
      <RefundEvidenceField
        :file="evidence.value.value"
        :upload-state="uploadState"
        :refusal="refusal"
        :error="evidence.errorMessage.value"
        :disabled="submitting"
        @select="selectEvidence"
      />
    </div>
  </FormModal>
</template>
