<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import IntegrationStatusBadge from './IntegrationStatusBadge.vue'
import { CREDENTIAL_SOURCES } from '@/constants/integrations'
import { integrationStatusOf } from '@/utils/integrations/integrationStatus'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  source: { type: String, required: true },
  configured: { type: Boolean, default: false },
  active: { type: Boolean, default: false },
  mode: { type: String, default: '' },
  lastTest: { type: Object, default: null },
})

const { t } = useI18n()
const status = computed(() =>
  integrationStatusOf({
    configured: props.configured,
    active: props.active,
    lastTest: props.lastTest,
  }),
)
const showSource = computed(() => props.source !== CREDENTIAL_SOURCES.NONE)
</script>

<template>
  <span class="integration-badges">
    <IntegrationStatusBadge :status="status" />
    <Badge
      v-if="showSource"
      :variant="source === CREDENTIAL_SOURCES.ENVIRONMENT ? 'warning' : 'muted'"
    >
      <AppIcon name="database" />
      {{ t(`integrations.source.${source}`) }}
    </Badge>
    <Badge v-if="mode" :variant="mode === 'live' ? 'info' : 'muted'">
      <AppIcon name="flask" />
      {{ t(`integrations.mode.${mode}`) }}
    </Badge>
  </span>
</template>
