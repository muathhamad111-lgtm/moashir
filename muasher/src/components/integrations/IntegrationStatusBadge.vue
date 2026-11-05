<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import {
  INTEGRATION_STATUS_ICONS,
  INTEGRATION_STATUS_VARIANTS,
} from '@/utils/integrations/integrationStatus'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  status: {
    type: String,
    required: true,
    validator: (value) => value in INTEGRATION_STATUS_VARIANTS,
  },
})

const { t } = useI18n()
const variant = computed(() => INTEGRATION_STATUS_VARIANTS[props.status])
const icon = computed(() => INTEGRATION_STATUS_ICONS[props.status])
</script>

<template>
  <Badge :variant="variant" class="integration-status">
    <AppIcon :name="icon" />
    {{ t(`integrations.status.${status}`) }}
  </Badge>
</template>
