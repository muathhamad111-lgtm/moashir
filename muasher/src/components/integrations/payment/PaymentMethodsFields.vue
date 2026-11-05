<script setup>
import { computed } from 'vue'
import { useFormValues } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import { CARD_NETWORKS, PAYMENT_LINKED_PATHS, PAYMENT_METHODS } from '@/constants/integrations'

const { t } = useI18n()
const values = useFormValues()

function lockedUntil(method, confirmed) {
  return confirmed !== true && !(values.value.methods ?? []).includes(method)
}

const methodOptions = computed(() => [
  { value: PAYMENT_METHODS.CREDIT_CARD, label: t('integrations.payment.methods.creditcard') },
  {
    value: PAYMENT_METHODS.APPLE_PAY,
    label: t('integrations.payment.methods.applepay'),
    disabled: lockedUntil(PAYMENT_METHODS.APPLE_PAY, values.value.confirmations?.applePayDomain),
  },
  {
    value: PAYMENT_METHODS.STC_PAY,
    label: t('integrations.payment.methods.stcpay'),
    disabled: lockedUntil(PAYMENT_METHODS.STC_PAY, values.value.confirmations?.stcpayActivation),
  },
])
const networkOptions = computed(() =>
  CARD_NETWORKS.map((value) => ({ value, label: t(`integrations.payment.networks.${value}`) })),
)
const pathOptions = computed(() =>
  PAYMENT_LINKED_PATHS.map((value) => ({
    value,
    label: t(`integrations.payment.linkedPaths.${value}`),
  })),
)
</script>

<template>
  <div class="payment-methods">
    <FieldCheckboxGroup
      name="methods"
      :label="t('integrations.payment.fields.methods')"
      :options="methodOptions"
      :hint="t('integrations.payment.fields.methodsHint')"
    />
    <FieldCheckboxGroup
      name="supportedNetworks"
      :label="t('integrations.payment.fields.networks')"
      :options="networkOptions"
    />
    <div class="payment-methods__label">
      <FieldInput
        name="applePayLabel.ar"
        :label="t('integrations.payment.fields.applePayLabelAr')"
        input-dir="rtl"
      />
      <FieldInput
        name="applePayLabel.en"
        :label="t('integrations.payment.fields.applePayLabelEn')"
        input-dir="ltr"
      />
    </div>
    <FieldCheckboxGroup
      name="linkedPaths"
      :label="t('integrations.payment.fields.linkedPaths')"
      :options="pathOptions"
    />
  </div>
</template>
