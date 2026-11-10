<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import EnvironmentSourceNote from '@/components/integrations/EnvironmentSourceNote.vue'
import ProviderStepsChecklist from '@/components/integrations/ProviderStepsChecklist.vue'
import SmsGeneralFields from '@/components/integrations/sms/SmsGeneralFields.vue'
import TaqnyatCard from '@/components/integrations/sms/TaqnyatCard.vue'
import MsegatCard from '@/components/integrations/sms/MsegatCard.vue'
import SmsTemplatesEditor from '@/components/integrations/sms/SmsTemplatesEditor.vue'
import SmsTestSend from '@/components/integrations/sms/SmsTestSend.vue'
import SmsBroadcastCard from '@/components/integrations/sms/SmsBroadcastCard.vue'
import { SHOWS_MODULES_AWAITING_BACKEND } from '@/constants/buildPosture'
import { useSmsIntegration } from '@/composables/useSmsIntegration'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { SMS_PROVIDERS } from '@/constants/integrations'

const { t } = useI18n()
const { can } = usePermissions()
const sms = useSmsIntegration()
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
} = sms
const {
  senders,
  activeSenders,
  msegatTemplates,
  approvedTemplates,
  templatesLoaded,
  templateRestricted,
  approvedBodies,
} = sms
const { providerStatus, generalStatus, clientSteps, defaultTestProvider } = sms

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('integrations.sms.title')"
    :description="t('integrations.sms.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form id="sms-integration-form" class="integration-form" novalidate @submit.prevent="submit">
      <EnvironmentSourceNote v-if="fromEnvironment" />
      <IntegrationCard :title="t('integrations.sms.generalTitle')" :status="generalStatus">
        <SmsGeneralFields />
      </IntegrationCard>
      <div class="integration-form__columns">
        <TaqnyatCard
          :config="resource.providers.taqnyat"
          :status="providerStatus(SMS_PROVIDERS.TAQNYAT)"
          :senders="senders"
          :active-senders="activeSenders"
          @tested="refresh"
        />
        <MsegatCard
          :config="resource.providers.msegat"
          :status="providerStatus(SMS_PROVIDERS.MSEGAT)"
          :templates="msegatTemplates"
          :approved-templates="approvedTemplates"
          :templates-loaded="templatesLoaded"
          @tested="refresh"
        />
      </div>
      <SmsTemplatesEditor
        :definitions="resource.templates"
        :approved-bodies="approvedBodies"
        :template-restricted="templateRestricted"
        :templates-loaded="templatesLoaded"
      />
      <ProviderStepsChecklist :title="t('integrations.clientStepsTitle')" :steps="clientSteps" />
    </form>

    <SmsTestSend :key="defaultTestProvider" :default-provider="defaultTestProvider" />
    <SmsBroadcastCard
      v-if="SHOWS_MODULES_AWAITING_BACKEND && can(PERMISSIONS.MESSAGING_CAMPAIGNS_SEND)"
    />

    <ReasonDialog
      v-model="reasonOpen"
      :title="t('integrations.reason.title')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      action-name="smsIntegration.reason"
    />

    <template #actions>
      <BaseButton
        type="submit"
        form="sms-integration-form"
        variant="primary"
        :loading="saving"
        icon="save"
      >
        {{ t('actions.save') }}
      </BaseButton>
    </template>
  </SettingsSection>
</template>
