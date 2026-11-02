<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { CYCLE_STATUSES } from '@/constants/advanced'

const props = defineProps({
  status: { type: String, required: true },
})

const VARIANTS = {
  [CYCLE_STATUSES.DRAFT]: 'muted',
  [CYCLE_STATUSES.SUBMISSION_OPEN]: 'success',
  [CYCLE_STATUSES.SUBMISSION_CLOSED]: 'warning',
  [CYCLE_STATUSES.COMMITTEE_ASSESSMENT]: 'info',
  [CYCLE_STATUSES.AWAITING_SECRETARY]: 'warning',
  [CYCLE_STATUSES.APPROVED]: 'info',
  [CYCLE_STATUSES.ANNOUNCED]: 'success',
  [CYCLE_STATUSES.CLOSED]: 'muted',
}

const { t } = useI18n()
const variant = computed(() => VARIANTS[props.status] ?? 'muted')
</script>

<template>
  <Badge :variant="variant">{{ t(`cycles.statuses.${status}`) }}</Badge>
</template>
