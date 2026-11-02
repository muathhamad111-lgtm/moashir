<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import { AI_PROVIDERS, ANTHROPIC_INFERENCE_GEOS, OPENAI_REGIONS } from '@/constants/integrations'

defineProps({
  provider: { type: String, required: true },
  prefix: { type: String, required: true },
})

const { t } = useI18n()
const regionOptions = computed(() => OPENAI_REGIONS.map((value) => ({ value, label: t(`integrations.ai.regions.${value}`) })))
const geoOptions = computed(() => ANTHROPIC_INFERENCE_GEOS.map((value) => ({ value, label: t(`integrations.ai.geos.${value}`) })))
</script>

<template>
  <div v-if="provider === AI_PROVIDERS.OPENAI" class="integration-form__grid">
    <FieldSelect :name="`${prefix}.region`" :label="t('integrations.ai.fields.region')" :options="regionOptions" :hint="t('integrations.ai.fields.regionHint')" />
    <FieldInput :name="`${prefix}.organizationId`" :label="t('integrations.ai.fields.organizationId')" input-dir="ltr" autocomplete="off" />
    <FieldInput :name="`${prefix}.projectId`" :label="t('integrations.ai.fields.projectId')" input-dir="ltr" autocomplete="off" />
  </div>
  <div v-else class="integration-form__grid">
    <FieldInput :name="`${prefix}.workspaceId`" :label="t('integrations.ai.fields.workspaceId')" :hint="t('integrations.ai.fields.workspaceHint')" input-dir="ltr" autocomplete="off" />
    <FieldSelect :name="`${prefix}.inferenceGeo`" :label="t('integrations.ai.fields.inferenceGeo')" :options="geoOptions" />
  </div>
</template>
