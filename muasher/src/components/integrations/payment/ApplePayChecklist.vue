<script setup>
import { computed } from 'vue'
import { useFormValues } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import ProviderStepsChecklist from '@/components/integrations/ProviderStepsChecklist.vue'
import ProviderActionFeedback from '@/components/integrations/ProviderActionFeedback.vue'
import RecordedStamp from '@/components/integrations/RecordedStamp.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import ApplePayAssociationFile from './ApplePayAssociationFile.vue'
import { PROVIDER_ACTION_STATES } from '@/composables/useProviderAction'

const props = defineProps({
  resource: { type: Object, required: true },
  actions: { type: Object, required: true },
})

const { t } = useI18n()
const values = useFormValues()
const fileActions = computed(() => [
  props.actions.fileUpload,
  props.actions.fileRemoval,
  props.actions.fileCheck,
])
const hasFile = computed(() => props.resource.applePayAssociationFile?.set === true)
const host = computed(() => props.resource.valuesToCopy?.applePayHost ?? '')
const confirmation = computed(() => props.resource.confirmations?.applePayDomain ?? null)
const checkResult = computed(() =>
  props.actions.fileCheck.state.value === PROVIDER_ACTION_STATES.SUCCESS
    ? props.actions.fileCheck.result.value
    : null,
)

const steps = computed(() => [
  { key: 'domain', label: t('integrations.payment.applePay.steps.domain', { host: host.value }) },
  { key: 'download', label: t('integrations.payment.applePay.steps.download') },
  { key: 'upload', label: t('integrations.payment.applePay.steps.upload'), done: hasFile.value },
  { key: 'validate', label: t('integrations.payment.applePay.steps.validate') },
  {
    key: 'confirm',
    label: t('integrations.payment.applePay.steps.confirm'),
    done: values.value.confirmations?.applePayDomain === true,
  },
])

const failure = computed(
  () =>
    fileActions.value.find((action) => action.state.value === PROVIDER_ACTION_STATES.FAILED)
      ?.failure.value ?? null,
)
const busy = computed(() =>
  fileActions.value.some((action) => action.state.value === PROVIDER_ACTION_STATES.PENDING),
)
</script>

<template>
  <ProviderStepsChecklist
    :title="t('integrations.payment.checklist.apple_pay_domain')"
    :steps="steps"
  >
    <ApplePayAssociationFile
      :file="resource.applePayAssociationFile"
      :check-result="checkResult"
      :busy="busy"
      @upload="actions.fileUpload.execute($event)"
      @remove="actions.fileRemoval.execute()"
      @check="actions.fileCheck.execute()"
    />
    <ProviderActionFeedback :failure="failure" />
    <FieldCheckbox
      name="confirmations.applePayDomain"
      :label="t('integrations.payment.applePay.confirmation')"
    />
    <RecordedStamp
      v-if="confirmation?.confirmed"
      kind="confirmed"
      :at="confirmation.confirmedAt"
      :by="confirmation.confirmedBy"
    />
  </ProviderStepsChecklist>
</template>
