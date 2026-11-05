<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { SCAN_STATUSES } from '@/constants/accredited'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  status: { type: String, required: true },
})

const PRESENTATION = {
  [SCAN_STATUSES.PENDING]: { variant: 'warning', icon: 'spinner' },
  [SCAN_STATUSES.CLEAN]: { variant: 'success', icon: 'shield-info' },
  [SCAN_STATUSES.INFECTED]: { variant: 'danger', icon: 'shield-info' },
  [SCAN_STATUSES.FAILED]: { variant: 'danger', icon: 'warning-triangle' },
}

const { t } = useI18n()
const presentation = computed(
  () => PRESENTATION[props.status] ?? PRESENTATION[SCAN_STATUSES.PENDING],
)
</script>

<template>
  <Badge :variant="presentation.variant" class="scan-status">
    <AppIcon :name="presentation.icon" />
    {{ t(`accredited.evidence.scan.${status}`) }}
  </Badge>
</template>
