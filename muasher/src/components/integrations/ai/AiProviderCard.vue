<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import ConnectionTestPanel from '@/components/integrations/ConnectionTestPanel.vue'
import FieldSecret from '@/components/form/FieldSecret.vue'
import AiProviderFields from './AiProviderFields.vue'
import AiModelFields from './AiModelFields.vue'
import AiAcknowledgementField from './AiAcknowledgementField.vue'
import AiTestAnswer from './AiTestAnswer.vue'
import { useAiProviderActions } from '@/composables/useAiProviderActions'

const props = defineProps({
  provider: { type: String, required: true },
  config: { type: Object, required: true },
  status: { type: Object, required: true },
})

const emit = defineEmits(['tested'])
const { t } = useI18n()
const { models, answer, loadedModels, runTest } = useAiProviderActions(props.provider, {
  onSettled: () => emit('tested'),
})
const prefix = computed(() => `providers.${props.provider}`)
const keyStored = computed(() => props.config.apiKey?.set === true)
</script>

<template>
  <IntegrationCard
    :title="t(`integrations.providers.${provider}`)"
    :description="t(`integrations.ai.providerDescriptions.${provider}`)"
    :status="status"
  >
    <FieldSecret
      :name="`${prefix}.apiKey`"
      :meta="config.apiKey"
      :label="t('integrations.ai.fields.apiKey')"
      :hint="t('integrations.ai.fields.apiKeyHint')"
    />
    <AiProviderFields :provider="provider" :prefix="prefix" />
    <AiModelFields
      :prefix="prefix"
      :models="models"
      :loaded-models="loadedModels"
      :key-stored="keyStored"
    />
    <AiAcknowledgementField
      :prefix="prefix"
      :acknowledgement="config.dataTransferAcknowledgement"
    />
    <ConnectionTestPanel
      :run="runTest"
      :disabled="!config.configured"
      :disabled-hint="t('integrations.ai.saveKeyAndModelFirst')"
      @settled="emit('tested')"
    />
    <AiTestAnswer :answer="answer" :disabled="!config.configured" />
  </IntegrationCard>
</template>
