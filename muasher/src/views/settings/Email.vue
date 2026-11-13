<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import EnvironmentSourceNote from '@/components/integrations/EnvironmentSourceNote.vue'
import ConnectionTestPanel from '@/components/integrations/ConnectionTestPanel.vue'
import ProviderStepsChecklist from '@/components/integrations/ProviderStepsChecklist.vue'
import SmtpFields from '@/components/integrations/email/SmtpFields.vue'
import EmailSenderFields from '@/components/integrations/email/EmailSenderFields.vue'
import DnsChecklist from '@/components/integrations/email/DnsChecklist.vue'
import EmailTestSend from '@/components/integrations/email/EmailTestSend.vue'
import EmailCampaignComposer from '@/components/integrations/email/EmailCampaignComposer.vue'
import { SHOWS_MODULES_AWAITING_BACKEND } from '@/constants/buildPosture'
import { useEmailIntegration } from '@/composables/useEmailIntegration'
import { usePermissions } from '@/composables/usePermissions'
import { emailIntegrationService } from '@/services/emailIntegrationService'
import { PERMISSIONS } from '@/constants/permissionKeys'

const { t } = useI18n()
const { can } = usePermissions()
const email = useEmailIntegration()
const {
  status,
  errorMessage,
  resource,
  fromEnvironment,
  saving,
  reasonOpen,
  load,
  submit,
  confirmReason,
} = email
const { onTested, dnsRecords, cardStatus, clientSteps } = email
const runTest = () => emailIntegrationService.testConnection()

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('integrations.email.title')"
    :description="t('integrations.email.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form id="email-integration-form" class="integration-form" novalidate @submit.prevent="submit">
      <EnvironmentSourceNote v-if="fromEnvironment" />
      <IntegrationCard
        :title="t('integrations.email.smtpTitle')"
        :description="t('integrations.email.smtpDesc')"
        :status="cardStatus"
      >
        <SmtpFields
          :password-meta="resource.password"
          :password-hint="
            fromEnvironment ? t('integrations.email.fields.passwordFromEnvironment') : ''
          "
        />
        <ConnectionTestPanel
          :run="runTest"
          :disabled="!resource.configured"
          :disabled-hint="t('integrations.feedback.saveDetailsFirst')"
          @settled="onTested"
        />
      </IntegrationCard>
      <IntegrationCard :title="t('integrations.email.senderTitle')">
        <EmailSenderFields />
      </IntegrationCard>
      <div class="integration-form__columns">
        <DnsChecklist :records="dnsRecords" />
        <ProviderStepsChecklist :title="t('integrations.clientStepsTitle')" :steps="clientSteps" />
      </div>
    </form>

    <EmailTestSend />
    <EmailCampaignComposer
      v-if="SHOWS_MODULES_AWAITING_BACKEND && can(PERMISSIONS.MESSAGING_CAMPAIGNS_SEND)"
    />

    <ReasonDialog
      v-model="reasonOpen"
      :title="t('integrations.reason.title')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      action-name="emailIntegration.reason"
    />

    <template #actions>
      <BaseButton
        type="submit"
        form="email-integration-form"
        variant="primary"
        :loading="saving"
        icon="save"
      >
        {{ t('actions.save') }}
      </BaseButton>
    </template>
  </SettingsSection>
</template>
