<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

defineProps({
  approval: { type: Object, default: null },
})

const { t } = useI18n()

const TONES = {
  approved: 'success',
  not_required: 'muted',
  pending: 'warning',
  templates_not_loaded: 'warning',
}
</script>

<template>
  <p v-if="!approval" class="notification-text__sms-note">
    {{ t('settings.notifications.texts.sms.unsaved') }}
  </p>
  <ul v-else class="notification-text__sms-approval">
    <li v-for="locale in ['ar', 'en']" :key="locale">
      <Badge :variant="TONES[approval[locale]] ?? 'muted'">
        {{ t(`settings.notifications.texts.sms.locales.${locale}`) }} —
        {{ t(`settings.notifications.texts.sms.approval.${approval[locale]}`) }}
      </Badge>
    </li>
  </ul>
</template>
