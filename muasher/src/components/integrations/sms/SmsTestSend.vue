<script setup>
import { computed } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import ProviderActionFeedback from '@/components/integrations/ProviderActionFeedback.vue'
import { smsIntegrationService } from '@/services/smsIntegrationService'
import { PROVIDER_ACTION_STATES, useProviderAction } from '@/composables/useProviderAction'
import { testRecipientSchema } from '@/utils/integrations/smsSchema'
import { SMS_PROVIDERS } from '@/constants/integrations'

const props = defineProps({
  defaultProvider: { type: String, default: SMS_PROVIDERS.TAQNYAT },
})

const { t } = useI18n()
const form = useForm({
  validationSchema: testRecipientSchema(t),
  initialValues: { recipient: '', provider: props.defaultProvider },
})
const sending = useProviderAction((payload) => smsIntegrationService.sendTest(payload))
const providerOptions = computed(() =>
  Object.values(SMS_PROVIDERS).map((value) => ({
    value,
    label: t(`integrations.providers.${value}`),
  })),
)
const sentThrough = computed(() => {
  const provider = sending.result.value?.provider
  return provider
    ? t('integrations.sms.testSent', { provider: t(`integrations.providers.${provider}`) })
    : ''
})
const send = form.handleSubmit((values) =>
  sending.execute({ recipient: values.recipient.trim(), provider: values.provider }),
)
</script>

<template>
  <IntegrationCard
    :title="t('integrations.sms.testSendTitle')"
    :description="t('integrations.sms.testSendDesc')"
  >
    <form class="integration-form__grid" novalidate @submit.prevent="send">
      <FieldInput
        name="recipient"
        type="tel"
        inputmode="tel"
        input-dir="ltr"
        autocomplete="off"
        :label="t('integrations.sms.fields.recipient')"
        :hint="t('integrations.sms.recipientHint')"
      />
      <FieldSelect
        name="provider"
        :label="t('integrations.sms.fields.provider')"
        :options="providerOptions"
      />
      <div class="integration-form__inline">
        <BaseButton
          type="submit"
          variant="secondary"
          icon="fa-solid fa-paper-plane"
          :loading="sending.state.value === PROVIDER_ACTION_STATES.PENDING"
        >
          {{ t('integrations.sms.sendTest') }}
        </BaseButton>
      </div>
    </form>
    <p
      v-if="sending.state.value === PROVIDER_ACTION_STATES.SUCCESS"
      class="provider-feedback provider-feedback--success"
      role="status"
    >
      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
      {{ sentThrough }}
    </p>
    <ProviderActionFeedback
      v-if="sending.state.value === PROVIDER_ACTION_STATES.FAILED"
      :failure="sending.failure.value"
    />
  </IntegrationCard>
</template>
