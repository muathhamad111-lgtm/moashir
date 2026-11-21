<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import EnvironmentSourceNote from '@/components/integrations/EnvironmentSourceNote.vue'
import ProviderStepsChecklist from '@/components/integrations/ProviderStepsChecklist.vue'
import AiProviderCard from '@/components/integrations/ai/AiProviderCard.vue'
import AiDataTransferNotice from '@/components/integrations/ai/AiDataTransferNotice.vue'
import AiAssistantFields from '@/components/integrations/ai/AiAssistantFields.vue'
import { useAiIntegration } from '@/composables/useAiIntegration'
import { AI_PROVIDERS } from '@/constants/integrations'

const PROVIDERS = Object.values(AI_PROVIDERS)

const { t } = useI18n()
const ai = useAiIntegration()
const {
  status,
  errorMessage,
  resource,
  fromEnvironment,
  saving,
  reasonOpen,
  load,
  refresh,
  submit,
  confirmReason,
} = ai
const { providerStatus, cardStatus, clientSteps } = ai

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('integrations.ai.title')"
    :description="t('integrations.ai.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form id="ai-integration-form" class="integration-form" novalidate @submit.prevent="submit">
      <EnvironmentSourceNote v-if="fromEnvironment" />
      <IntegrationCard
        :title="t('integrations.ai.assistantTitle')"
        :description="t('integrations.ai.assistantDesc')"
        :status="cardStatus"
      >
        <AiDataTransferNotice />
        <AiAssistantFields />
      </IntegrationCard>
      <div class="integration-form__columns">
        <AiProviderCard
          v-for="provider in PROVIDERS"
          :key="provider"
          :provider="provider"
          :config="resource.providers[provider]"
          :status="providerStatus(provider)"
          @tested="refresh"
        />
      </div>
      <ProviderStepsChecklist :title="t('integrations.clientStepsTitle')" :steps="clientSteps" />
    </form>

    <ReasonDialog
      v-model="reasonOpen"
      :title="t('integrations.reason.title')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      action-name="aiIntegration.reason"
    />

    <template #actions>
      <BaseButton
        type="submit"
        form="ai-integration-form"
        variant="primary"
        :loading="saving"
        icon="save"
      >
        {{ t('actions.save') }}
      </BaseButton>
    </template>
  </SettingsSection>
</template>
