<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { MESSAGE_STATUS_TONES } from '@/constants/messages'

const props = defineProps({
  status: { type: String, required: true },
})

const { t } = useI18n()
const tone = computed(() => MESSAGE_STATUS_TONES[props.status] ?? 'muted')
</script>

<template>
  <Badge :variant="tone">{{ t(`portal.messages.status.${status}`) }}</Badge>
</template>
