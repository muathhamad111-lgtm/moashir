<script setup>
import { computed } from 'vue'
import { useFormValues } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import ProviderStepsChecklist from '@/components/integrations/ProviderStepsChecklist.vue'
import RecordedStamp from '@/components/integrations/RecordedStamp.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'

const props = defineProps({
  confirmation: { type: Object, default: null },
})

const { t } = useI18n()
const values = useFormValues()
const steps = computed(() => [
  { key: 'request', label: t('integrations.payment.stcPay.steps.request') },
  {
    key: 'confirm',
    label: t('integrations.payment.stcPay.steps.confirm'),
    done: values.value.confirmations?.stcpayActivation === true,
  },
])
</script>

<template>
  <ProviderStepsChecklist
    :title="t('integrations.payment.checklist.stcpay_activation')"
    :steps="steps"
  >
    <FieldCheckbox
      name="confirmations.stcpayActivation"
      :label="t('integrations.payment.stcPay.confirmation')"
    />
    <RecordedStamp
      v-if="props.confirmation?.confirmed"
      kind="confirmed"
      :at="props.confirmation.confirmedAt"
      :by="props.confirmation.confirmedBy"
    />
  </ProviderStepsChecklist>
</template>
