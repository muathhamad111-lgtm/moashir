<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import IntegrationCard from '@/components/integrations/IntegrationCard.vue'
import ConnectionTestPanel from '@/components/integrations/ConnectionTestPanel.vue'
import ProviderActionFeedback from '@/components/integrations/ProviderActionFeedback.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSecret from '@/components/form/FieldSecret.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import MsegatTemplatesList from './MsegatTemplatesList.vue'
import { smsIntegrationService } from '@/services/smsIntegrationService'
import { PROVIDER_ACTION_STATES } from '@/composables/useProviderAction'
import { SMS_PROVIDERS } from '@/constants/integrations'

const props = defineProps({
  config: { type: Object, required: true },
  status: { type: Object, required: true },
  templates: { type: Object, required: true },
  approvedTemplates: { type: Array, required: true },
  templatesLoaded: { type: Boolean, default: false },
})

const emit = defineEmits(['tested'])
const { t } = useI18n()
const keyStored = computed(() => props.config.apiKey?.set === true)
const runTest = () => smsIntegrationService.testConnection(SMS_PROVIDERS.MSEGAT)
</script>

<template>
  <IntegrationCard :title="t('integrations.providers.msegat')" :description="t('integrations.sms.msegat.description')" :status="status">
    <div class="integration-form__grid">
      <FieldInput name="providers.msegat.username" :label="t('integrations.sms.msegat.username')" input-dir="ltr" autocomplete="off" />
      <FieldInput name="providers.msegat.senderName" :label="t('integrations.sms.fields.senderName')" :hint="t('integrations.sms.msegat.senderHint')" input-dir="ltr" autocomplete="off" />
    </div>
    <FieldSecret name="providers.msegat.apiKey" :meta="config.apiKey" :label="t('integrations.sms.msegat.apiKey')" />
    <FieldCheckbox name="providers.msegat.templateRestricted" :label="t('integrations.sms.msegat.templateRestricted')" />
    <p class="field__hint">{{ t('integrations.sms.msegat.templateRestrictedHint') }}</p>
    <MsegatTemplatesList
      :action="templates"
      :items="approvedTemplates"
      :loaded="templatesLoaded"
      :loaded-at="config.approvedTemplatesLoadedAt"
      :disabled="!keyStored"
    />
    <ProviderActionFeedback v-if="templates.state.value === PROVIDER_ACTION_STATES.FAILED" :failure="templates.failure.value" />
    <ConnectionTestPanel :run="runTest" :disabled="!config.configured" :disabled-hint="t('integrations.feedback.saveDetailsFirst')" @settled="emit('tested')" />
  </IntegrationCard>
</template>
