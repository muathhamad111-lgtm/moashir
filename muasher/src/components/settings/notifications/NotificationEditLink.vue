<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { notificationTypeName } from '@/utils/notificationTypeName'

const props = defineProps({
  code: { type: String, required: true },
  channel: { type: String, default: '' },
})

const { t, te } = useI18n()
const to = computed(() => ({
  name: ROUTE_NAMES.ADMIN_SETTINGS_NOTIFICATION_TEXTS,
  params: { code: props.code },
  query: props.channel ? { channel: props.channel } : {},
}))
</script>

<template>
  <router-link
    class="btn btn--ghost btn--sm notification-edit-link"
    :to="to"
    :aria-label="
      t('settings.notifications.catalogue.editLabel', {
        name: notificationTypeName(code, t, te),
      })
    "
  >
    {{ t('settings.notifications.catalogue.edit') }}
    <AppIcon name="chevron-sm-right" />
  </router-link>
</template>
