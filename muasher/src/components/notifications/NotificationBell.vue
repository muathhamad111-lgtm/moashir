<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'
import { isInternalPath } from '@/utils/internalPath'
import AppPopover from '@/components/ui/AppPopover.vue'
import NotificationPanel from './NotificationPanel.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const router = useRouter()
const popoverRef = ref(null)
const { items, unreadCount, status, load, markRead, markAllRead } = useNotifications()

async function onOpenItem(item) {
  popoverRef.value?.dismiss()
  await markRead(item.id).catch(() => null)
  if (isInternalPath(item.link)) router.push(item.link)
}
</script>

<template>
  <AppPopover
    ref="popoverRef"
    class="notification-bell"
    :label="t('components.notifications.title')"
    :trigger-label="t('components.notifications.bellLabel', { count: unreadCount })"
  >
    <template #trigger>
      <AppIcon name="bell" />
      <span v-if="unreadCount > 0" class="notification-bell__count" aria-hidden="true">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </template>
    <template #default="{ headingId, isSheet }">
      <NotificationPanel
        :heading-id="headingId"
        :show-title="!isSheet"
        :items="items"
        :status="status"
        :unread-count="unreadCount"
        @open="onOpenItem"
        @mark-all-read="markAllRead"
        @retry="load"
      />
    </template>
  </AppPopover>
</template>
