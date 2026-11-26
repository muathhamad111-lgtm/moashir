<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { CONTACT_INBOX_STATUS_TONES } from '@/constants/contactInbox'

const props = defineProps({
  status: { type: String, required: true },
})

const { t } = useI18n()
const tone = computed(() => CONTACT_INBOX_STATUS_TONES[props.status] ?? 'muted')
</script>

<template>
  <Badge class="contact-status" :variant="tone">{{ t(`contactInbox.statuses.${status}`) }}</Badge>
</template>
