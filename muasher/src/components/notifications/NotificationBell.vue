<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisclosure } from '@/composables/useDisclosure'
import { useNotifications } from '@/composables/useNotifications'
import { isInternalPath } from '@/utils/internalPath'
import NotificationPanel from './NotificationPanel.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const router = useRouter()
const { isOpen, rootRef, toggle, close } = useDisclosure()
const { items, unreadCount, status, load, markRead, markAllRead } = useNotifications()

async function onOpenItem(item) {
  close()
  await markRead(item.id).catch(() => null)
  if (isInternalPath(item.link)) router.push(item.link)
}
</script>

<template>
  <div ref="rootRef" class="notification-bell">
    <button
      type="button"
      class="btn btn--ghost btn--sm btn--icon"
      :aria-label="t('components.notifications.bellLabel', { count: unreadCount })"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      @click="toggle"
    >
      <AppIcon name="bell" />
      <span v-if="unreadCount > 0" class="notification-bell__count" aria-hidden="true">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>
    <NotificationPanel
      v-if="isOpen"
      :items="items"
      :status="status"
      :unread-count="unreadCount"
      @open="onOpenItem"
      @mark-all-read="markAllRead"
      @retry="load"
    />
  </div>
</template>
