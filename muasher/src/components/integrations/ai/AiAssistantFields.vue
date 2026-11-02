<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldRadio from '@/components/form/FieldRadio.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { AI_PLACEMENTS, AI_PROVIDERS, INTEGRATION_MODES, NO_ACTIVE_PROVIDER } from '@/constants/integrations'

const { t } = useI18n()
const providerOptions = computed(() =>
  [NO_ACTIVE_PROVIDER, ...Object.values(AI_PROVIDERS)].map((value) => ({ value, label: t(`integrations.ai.activeProviders.${value}`) })),
)
const modeOptions = computed(() => Object.values(INTEGRATION_MODES).map((value) => ({ value, label: t(`integrations.mode.${value}`) })))
const placementOptions = computed(() => AI_PLACEMENTS.map((value) => ({ value, label: t(`integrations.ai.placements.${value}`) })))
</script>

<template>
  <div class="ai-assistant">
    <FieldRadio name="activeProvider" :label="t('integrations.ai.fields.activeProvider')" :options="providerOptions" />
    <p class="field__hint">{{ t('integrations.ai.activationHint') }}</p>
    <FieldRadio name="mode" :label="t('integrations.ai.fields.mode')" :options="modeOptions" />
    <p class="field__hint">{{ t('integrations.ai.modeHint') }}</p>
    <FieldSelect name="placement" :label="t('integrations.ai.fields.placement')" :options="placementOptions" />
    <div class="integration-form__grid">
      <FieldTextarea name="notice.ar" :label="t('integrations.ai.fields.noticeAr')" :rows="3" />
      <FieldTextarea name="notice.en" :label="t('integrations.ai.fields.noticeEn')" :rows="3" />
    </div>
  </div>
</template>
