<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { SCAN_STATUSES } from '@/constants/accredited'

const props = defineProps({
  status: { type: String, required: true },
})

const PRESENTATION = {
  [SCAN_STATUSES.PENDING]: { variant: 'warning', icon: 'fa-solid fa-spinner fa-spin' },
  [SCAN_STATUSES.CLEAN]: { variant: 'success', icon: 'fa-solid fa-shield-check' },
  [SCAN_STATUSES.INFECTED]: { variant: 'danger', icon: 'fa-solid fa-shield-virus' },
  [SCAN_STATUSES.FAILED]: { variant: 'danger', icon: 'fa-solid fa-circle-exclamation' },
}

const { t } = useI18n()
const presentation = computed(() => PRESENTATION[props.status] ?? PRESENTATION[SCAN_STATUSES.PENDING])
</script>

<template>
  <Badge :variant="presentation.variant" class="scan-status">
    <i :class="presentation.icon" aria-hidden="true"></i>
    {{ t(`accredited.evidence.scan.${status}`) }}
  </Badge>
</template>
