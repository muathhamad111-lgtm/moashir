<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldRadio from '@/components/form/FieldRadio.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import { INTEGRATION_MODES } from '@/constants/integrations'

const props = defineProps({
  liveReady: { type: Boolean, default: false },
})

const { t } = useI18n()
const modeOptions = computed(() => [
  { value: INTEGRATION_MODES.TEST, label: t('integrations.mode.test') },
  { value: INTEGRATION_MODES.LIVE, label: t('integrations.mode.live'), disabled: !props.liveReady },
])
</script>

<template>
  <div class="payment-environment">
    <FieldRadio
      name="mode"
      :label="t('integrations.payment.fields.environment')"
      :options="modeOptions"
    />
    <p class="field__hint">
      {{ liveReady ? t('integrations.payment.liveReady') : t('integrations.payment.liveNotReady') }}
    </p>
    <p class="field__hint">{{ t('integrations.payment.productionReasonHint') }}</p>
    <FieldToggle
      name="active"
      :label="t('integrations.payment.fields.active')"
      :description="t('integrations.payment.fields.activeHint')"
    />
  </div>
</template>
