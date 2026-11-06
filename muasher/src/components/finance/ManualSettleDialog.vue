<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { useManualSettle } from '@/composables/useManualSettle'
import { MANUAL_SETTLE_RULES } from '@/constants/finance'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  transaction: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'settled'])
const { t } = useI18n()

const { submitting, submit } = useManualSettle(props.transaction.id, {
  onSettled: (detail) => {
    emit('update:modelValue', false)
    emit('settled', detail)
  },
})

const statusOptions = computed(() =>
  MANUAL_SETTLE_RULES.STATUSES.map((value) => ({
    value,
    label: t(`finance.settle.statuses.${value}`),
  })),
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('finance.settle.title')"
    :busy="submitting"
    :submit-label="t('finance.settle.submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
  >
    <div class="settle-form">
      <p class="settle-form__lead" role="note">
        <AppIcon name="user-shield" />
        {{ t('finance.settle.lead', { reference: transaction.reference }) }}
      </p>
      <FieldRadio name="status" :label="t('finance.settle.status')" :options="statusOptions" />
      <FieldInput
        name="providerPaymentId"
        :label="t('finance.settle.providerPaymentId')"
        :hint="t('finance.settle.providerPaymentIdHint')"
        input-dir="ltr"
      />
      <FieldTextarea
        name="reason"
        :label="t('finance.settle.reason')"
        :hint="t('finance.settle.reasonHint', { min: MANUAL_SETTLE_RULES.REASON_MIN })"
        :rows="4"
        required
      />
    </div>
  </FormModal>
</template>
