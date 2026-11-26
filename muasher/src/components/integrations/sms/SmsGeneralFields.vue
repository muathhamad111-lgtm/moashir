<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldRadio from '@/components/form/FieldRadio.vue'
import FieldPhoneList from '@/components/form/FieldPhoneList.vue'
import {
  INTEGRATION_LIMITS,
  INTEGRATION_MODES,
  NO_ACTIVE_PROVIDER,
  SMS_PROVIDERS,
} from '@/constants/integrations'

const { t } = useI18n()
const providerOptions = computed(() =>
  [NO_ACTIVE_PROVIDER, ...Object.values(SMS_PROVIDERS)].map((value) => ({
    value,
    label: t(`integrations.sms.activeProviders.${value}`),
  })),
)
const modeOptions = computed(() =>
  Object.values(INTEGRATION_MODES).map((value) => ({
    value,
    label: t(`integrations.mode.${value}`),
  })),
)
</script>

<template>
  <div class="sms-general">
    <FieldRadio
      name="activeProvider"
      :label="t('integrations.sms.fields.activeProvider')"
      :options="providerOptions"
    />
    <FieldRadio name="mode" :label="t('integrations.sms.fields.mode')" :options="modeOptions" />
    <p class="field__hint">{{ t('integrations.sms.modeHint') }}</p>
    <FieldPhoneList
      name="testRecipients"
      :label="t('integrations.sms.fields.testRecipients')"
      :max="INTEGRATION_LIMITS.TEST_RECIPIENTS_MAX"
      :hint="
        t('integrations.sms.testRecipientsHint', { max: INTEGRATION_LIMITS.TEST_RECIPIENTS_MAX })
      "
    />
  </div>
</template>
