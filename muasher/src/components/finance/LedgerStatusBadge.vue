<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { LEDGER_STATUS_VARIANTS } from '@/constants/finance'

// A ledger status (the operations filter's status) with its colour.
const props = defineProps({
  status: { type: String, required: true },
  // The overview cards use the short name where one exists (their cells are narrow).
  short: { type: Boolean, default: false },
})

const { t, te } = useI18n()
const variant = computed(() => LEDGER_STATUS_VARIANTS[props.status] ?? 'muted')
const label = computed(() => {
  const shortKey = `finance.page.cards.statuses.${props.status}`
  return props.short && te(shortKey)
    ? t(shortKey)
    : t(`finance.operations.statuses.${props.status}.label`)
})
</script>

<template>
  <Badge :variant="variant">{{ label }}</Badge>
</template>
