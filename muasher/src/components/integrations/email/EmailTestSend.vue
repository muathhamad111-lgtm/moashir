<script setup>
import { computed } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import ProviderActionFeedback from '@/components/integrations/ProviderActionFeedback.vue'
import { emailIntegrationService } from '@/services/emailIntegrationService'
import { PROVIDER_ACTION_STATES, useProviderAction } from '@/composables/useProviderAction'
import { testEmailSchema } from '@/utils/integrations/emailSchema'

const { t } = useI18n()
const form = useForm({ validationSchema: testEmailSchema(t), initialValues: { recipient: '' } })
const sending = useProviderAction((payload) => emailIntegrationService.sendTest(payload))
const sentMessage = computed(() =>
  t(
    sending.result.value?.redirectedToTestMailbox
      ? 'integrations.email.testRedirected'
      : 'integrations.email.testSent',
  ),
)
const send = form.handleSubmit((values) => sending.execute({ recipient: values.recipient.trim() }))
</script>

<template>
  <IntegrationCard
    :title="t('integrations.email.testSendTitle')"
    :description="t('integrations.email.testSendDesc')"
  >
    <form class="integration-form__grid" novalidate @submit.prevent="send">
      <FieldInput
        name="recipient"
        type="email"
        input-dir="ltr"
        autocomplete="off"
        :label="t('integrations.email.fields.recipient')"
      />
      <div class="integration-form__inline">
        <BaseButton
          type="submit"
          variant="secondary"
          icon="fa-solid fa-paper-plane"
          :loading="sending.state.value === PROVIDER_ACTION_STATES.PENDING"
        >
          {{ t('integrations.email.sendTest') }}
        </BaseButton>
      </div>
    </form>
    <p
      v-if="sending.state.value === PROVIDER_ACTION_STATES.SUCCESS"
      class="provider-feedback provider-feedback--success"
      role="status"
    >
      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
      {{ sentMessage }}
    </p>
    <ProviderActionFeedback
      v-if="sending.state.value === PROVIDER_ACTION_STATES.FAILED"
      :failure="sending.failure.value"
    />
  </IntegrationCard>
</template>
