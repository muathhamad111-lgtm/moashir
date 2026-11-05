<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSecret from '@/components/form/FieldSecret.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import IntegrationStatusBadge from '@/components/integrations/IntegrationStatusBadge.vue'
import LastTestLine from '@/components/integrations/LastTestLine.vue'
import RecordedStamp from '@/components/integrations/RecordedStamp.vue'
import ConnectionTestPanel from '@/components/integrations/ConnectionTestPanel.vue'
import ProviderActionFeedback from '@/components/integrations/ProviderActionFeedback.vue'
import { paymentIntegrationService } from '@/services/paymentIntegrationService'
import { PROVIDER_ACTION_STATES } from '@/composables/useProviderAction'
import { integrationStatusOf } from '@/utils/integrations/integrationStatus'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  mode: { type: String, required: true },
  pair: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  webhook: { type: Object, required: true },
})

const emit = defineEmits(['tested'])
const { t } = useI18n()
const ownsWebhookAction = computed(() => props.webhook.mode.value === props.mode)
const registering = computed(
  () =>
    ownsWebhookAction.value && props.webhook.action.state.value === PROVIDER_ACTION_STATES.PENDING,
)
const webhookFailure = computed(() =>
  ownsWebhookAction.value && props.webhook.action.state.value === PROVIDER_ACTION_STATES.FAILED
    ? props.webhook.action.failure.value
    : null,
)
const status = computed(() => integrationStatusOf(props.pair))
const runTest = () => paymentIntegrationService.testConnection(props.mode)
</script>

<template>
  <section
    class="key-pair"
    :class="{ 'is-selected': selected }"
    :aria-labelledby="`key-pair-${mode}`"
  >
    <header class="key-pair__header">
      <h3 :id="`key-pair-${mode}`" class="key-pair__title">
        {{ t(`integrations.payment.pairs.${mode}`) }}
      </h3>
      <IntegrationStatusBadge :status="status" />
      <Badge v-if="selected" variant="success">{{ t('integrations.payment.selectedMode') }}</Badge>
    </header>
    <LastTestLine :last-test="pair.lastTest" />
    <div class="key-pair__fields">
      <FieldInput
        :name="`${mode}.publishableKey`"
        :label="t(`integrations.payment.fields.publishableKey.${mode}`)"
        input-dir="ltr"
        autocomplete="off"
      />
      <FieldSecret
        :name="`${mode}.secretKey`"
        :meta="pair.secretKey"
        :label="t(`integrations.payment.fields.secretKey.${mode}`)"
      />
      <FieldSecret
        :name="`${mode}.webhookSecret`"
        :meta="pair.webhookSecret"
        :label="t('integrations.payment.fields.webhookSecret')"
        :hint="t('integrations.payment.fields.webhookSecretHint')"
      />
    </div>
    <div class="key-pair__webhook">
      <Badge :variant="pair.webhook ? 'success' : 'warning'">
        <AppIcon :name="pair.webhook ? 'link' : 'unlink'" />
        {{
          t(
            pair.webhook
              ? 'integrations.payment.webhook.registered'
              : 'integrations.payment.webhook.notRegistered',
          )
        }}
      </Badge>
      <BaseButton
        variant="ghost"
        size="sm"
        icon="broadcast"
        :loading="registering"
        :disabled="!pair.configured"
        @click="webhook.register(mode)"
      >
        {{ t('integrations.payment.webhook.register') }}
      </BaseButton>
    </div>
    <RecordedStamp v-if="pair.webhook" kind="registered" :at="pair.webhook.registeredAt" />
    <ProviderActionFeedback :failure="webhookFailure" />
    <ConnectionTestPanel
      :run="runTest"
      :disabled="!pair.configured"
      :disabled-hint="t('integrations.feedback.saveKeysFirst')"
      @settled="emit('tested')"
    />
  </section>
</template>
