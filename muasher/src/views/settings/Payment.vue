<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import TestModeBanner from '@/components/integrations/TestModeBanner.vue'
import EnvironmentSourceNote from '@/components/integrations/EnvironmentSourceNote.vue'
import ValuesToCopyPanel from '@/components/integrations/ValuesToCopyPanel.vue'
import ProviderStepsChecklist from '@/components/integrations/ProviderStepsChecklist.vue'
import PaymentEnvironmentFields from '@/components/integrations/payment/PaymentEnvironmentFields.vue'
import PaymentKeyPairCard from '@/components/integrations/payment/PaymentKeyPairCard.vue'
import PaymentMethodsFields from '@/components/integrations/payment/PaymentMethodsFields.vue'
import ApplePayChecklist from '@/components/integrations/payment/ApplePayChecklist.vue'
import StcPayChecklist from '@/components/integrations/payment/StcPayChecklist.vue'
import { usePaymentIntegration } from '@/composables/usePaymentIntegration'
import { usePermissions } from '@/composables/usePermissions'
import { INTEGRATION_MODES } from '@/constants/integrations'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'

const MODES = [INTEGRATION_MODES.TEST, INTEGRATION_MODES.LIVE]

const { t } = useI18n()
const { can } = usePermissions()
const payment = usePaymentIntegration()
const { status, errorMessage, resource, fromEnvironment, form, saving, reasonOpen, load, refresh, submit, confirmReason } = payment
const { webhook, fileUpload, fileRemoval, fileCheck, liveReady, testModeBanner, copyItems, clientSteps, cardStatus } = payment
const { reasonOpen: webhookReasonOpen, confirmReason: confirmWebhookReason } = webhook
const fileActions = { fileUpload, fileRemoval, fileCheck }

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('integrations.payment.title')"
    :description="t('integrations.payment.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form id="payment-integration-form" class="integration-form" novalidate @submit.prevent="submit">
      <TestModeBanner v-if="testModeBanner" />
      <EnvironmentSourceNote v-if="fromEnvironment" />
      <IntegrationCard :title="t('integrations.payment.gatewayTitle')" :description="t('integrations.payment.gatewayDesc')" :status="cardStatus">
        <PaymentEnvironmentFields :live-ready="liveReady" />
        <div class="integration-form__pairs">
          <PaymentKeyPairCard
            v-for="mode in MODES"
            :key="mode"
            :mode="mode"
            :pair="resource[mode]"
            :selected="form.values.mode === mode"
            :webhook="webhook"
            @tested="refresh"
          />
        </div>
      </IntegrationCard>

      <IntegrationCard :title="t('integrations.payment.methodsTitle')">
        <PaymentMethodsFields />
      </IntegrationCard>

      <div class="integration-form__columns">
        <ApplePayChecklist :resource="resource" :actions="fileActions" />
        <StcPayChecklist :confirmation="resource.confirmations?.stcpayActivation" />
      </div>

      <ValuesToCopyPanel :items="copyItems" />
      <ProviderStepsChecklist :title="t('integrations.payment.clientStepsTitle')" :steps="clientSteps" />
    </form>

    <p v-if="can(PERMISSIONS.SETTINGS_PAYMENT_MANAGE)" class="integration-form__events-link">
      <RouterLink :to="{ name: ROUTE_NAMES.FINANCE_PAYMENT_EVENTS }">{{ t('finance.events.openLog') }}</RouterLink>
    </p>

    <ReasonDialog
      v-model="reasonOpen"
      :title="t('integrations.reason.title')"
      :message="t('integrations.reason.message')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      action-name="paymentIntegration.reason"
    />
    <ReasonDialog
      v-model="webhookReasonOpen"
      :title="t('integrations.reason.title')"
      :message="t('integrations.payment.webhook.reasonMessage')"
      :submit-label="t('integrations.payment.webhook.register')"
      :action="confirmWebhookReason"
      action-name="paymentIntegration.webhookReason"
    />

    <template #actions>
      <BaseButton type="submit" form="payment-integration-form" variant="primary" :loading="saving" icon="fa-solid fa-floppy-disk">
        {{ t('actions.save') }}
      </BaseButton>
    </template>
  </SettingsSection>
</template>
