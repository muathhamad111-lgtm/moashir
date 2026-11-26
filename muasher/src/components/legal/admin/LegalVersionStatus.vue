<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

const TONES = { draft: 'warning', current: 'success', superseded: 'muted' }

const props = defineProps({
  status: { type: String, required: true },
})

const { t } = useI18n()
const tone = computed(() => TONES[props.status] ?? 'muted')
</script>

<template>
  <Badge :variant="tone">{{ t(`legalAdmin.statuses.${status}`) }}</Badge>
</template>
