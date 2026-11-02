<script setup>
import { useI18n } from 'vue-i18n'
import { useDisclosure } from '@/composables/useDisclosure'
import { useNotifications } from '@/composables/useNotifications'
import NotificationPanel from './NotificationPanel.vue'

const { t } = useI18n()
const { isOpen, rootRef, toggle, close } = useDisclosure()
const { items, unreadCount, status, load, markRead, markAllRead } = useNotifications()

async function onOpenItem(item) {
  close()
  await markRead(item.id)
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
      <i class="fa-solid fa-bell" aria-hidden="true"></i>
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
