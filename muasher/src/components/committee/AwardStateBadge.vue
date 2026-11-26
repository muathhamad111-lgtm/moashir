<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { toCanonicalStatus } from '@/utils/canonicalStatus'

const props = defineProps({
  kind: { type: String, required: true },
  status: { type: String, required: true },
})

const VARIANTS = {
  seat: {
    active: 'success',
    disabled: 'warning',
    excluded: 'danger',
    substituted: 'muted',
    removed: 'muted',
  },
  row: { 'not-started': 'muted', 'in-progress': 'info', completed: 'success', voided: 'muted' },
  discussion: { open: 'info', closed: 'success', cancelled: 'muted' },
}

const { t } = useI18n()
const variant = computed(() => VARIANTS[props.kind]?.[props.status] ?? 'muted')
</script>

<template>
  <Badge :variant="variant">{{ t(`award.states.${kind}.${toCanonicalStatus(status)}`) }}</Badge>
</template>
