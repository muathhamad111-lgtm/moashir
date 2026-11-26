<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

const TONES = { accepted: 'success', pending: 'warning', 'out-of-scope': 'muted' }

defineProps({
  status: { type: String, default: null },
  version: { type: String, default: null },
  // Inside another cell (the users list status), the badge names what it is about.
  labelled: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <Badge v-if="status" :variant="TONES[status] ?? 'muted'" data-testid="nda-status">
    <template v-if="labelled">{{ t('nda.users.column') }}: </template>
    <bdi v-if="status === 'accepted' && version" dir="ltr">{{ version }}</bdi>
    <template v-else>{{ t(`nda.users.statuses.${status}`) }}</template>
  </Badge>
</template>
