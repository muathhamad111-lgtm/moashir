<script setup>
import { computed } from 'vue'
import { useFormValues } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import ConnectionTestPanel from '@/components/integrations/ConnectionTestPanel.vue'
import ProviderActionFeedback from '@/components/integrations/ProviderActionFeedback.vue'
import FieldSecret from '@/components/form/FieldSecret.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { smsIntegrationService } from '@/services/smsIntegrationService'
import { PROVIDER_ACTION_STATES } from '@/composables/useProviderAction'
import { SMS_PROVIDERS } from '@/constants/integrations'

const props = defineProps({
  config: { type: Object, required: true },
  status: { type: Object, required: true },
  senders: { type: Object, required: true },
  activeSenders: { type: Array, required: true },
})

const emit = defineEmits(['tested'])
const { t } = useI18n()
const values = useFormValues()
const tokenStored = computed(() => props.config.bearerToken?.set === true)
const loaded = computed(() => props.senders.state.value === PROVIDER_ACTION_STATES.SUCCESS)
const senderOptions = computed(() => {
  const names = new Set(props.activeSenders.map((sender) => sender.senderName))
  const current = values.value.providers?.taqnyat?.senderName
  if (current) names.add(current)
  return [...names].map((name) => ({ value: name, label: name }))
})
const runTest = () => smsIntegrationService.testConnection(SMS_PROVIDERS.TAQNYAT)
</script>

<template>
  <IntegrationCard
    :title="t('integrations.providers.taqnyat')"
    :description="t('integrations.sms.taqnyat.description')"
    :status="status"
  >
    <FieldSecret
      name="providers.taqnyat.bearerToken"
      :meta="config.bearerToken"
      :label="t('integrations.sms.taqnyat.bearerToken')"
    />
    <div class="sms-sender">
      <FieldSelect
        name="providers.taqnyat.senderName"
        :label="t('integrations.sms.fields.senderName')"
        :options="senderOptions"
        :hint="t('integrations.sms.taqnyat.senderHint')"
      />
      <BaseButton
        variant="secondary"
        size="sm"
        icon="refresh"
        :loading="senders.state.value === PROVIDER_ACTION_STATES.PENDING"
        :disabled="!tokenStored"
        @click="senders.execute()"
      >
        {{ t('integrations.sms.taqnyat.loadSenders') }}
      </BaseButton>
    </div>
    <p v-if="loaded && !activeSenders.length" class="sms-sender__empty" role="status">
      {{ t('integrations.sms.taqnyat.noSenders') }}
    </p>
    <p v-else-if="loaded" class="field__hint" role="status">
      {{ t('integrations.sms.taqnyat.sendersLoaded', { count: activeSenders.length }) }}
    </p>
    <ProviderActionFeedback
      v-if="senders.state.value === PROVIDER_ACTION_STATES.FAILED"
      :failure="senders.failure.value"
    />
    <ConnectionTestPanel
      :run="runTest"
      :disabled="!config.configured"
      :disabled-hint="t('integrations.feedback.saveDetailsFirst')"
      @settled="emit('tested')"
    />
  </IntegrationCard>
</template>
