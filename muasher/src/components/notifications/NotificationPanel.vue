<script setup>
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import NotificationItem from './NotificationItem.vue'

defineProps({
  headingId: { type: String, default: '' },
  showTitle: { type: Boolean, default: true },
  items: { type: Array, default: () => [] },
  status: { type: String, default: 'loading' },
  unreadCount: { type: Number, default: 0 },
})

const emit = defineEmits(['open', 'mark-all-read', 'retry'])
const { t } = useI18n()
const notificationsRoute = { name: ROUTE_NAMES.NOTIFICATIONS_INDEX }
</script>

<template>
  <div class="notification-panel">
    <header v-if="showTitle || unreadCount > 0" class="notification-panel__header">
      <h2 v-if="showTitle" :id="headingId" class="notification-panel__title" tabindex="-1">
        {{ t('components.notifications.title') }}
      </h2>
      <BaseButton v-if="unreadCount > 0" variant="ghost" size="sm" @click="emit('mark-all-read')">
        {{ t('components.notifications.markAllRead') }}
      </BaseButton>
    </header>
    <div v-if="status === 'loading'" class="notification-panel__state">
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status === 'error'" @retry="emit('retry')" />
    <EmptyState
      v-else-if="status === 'empty'"
      icon="bell"
      :title="t('components.notifications.emptyTitle')"
      :message="t('components.notifications.emptyMessage')"
    />
    <div v-else class="notification-panel__list">
      <NotificationItem
        v-for="item in items"
        :key="item.id"
        :notification="item"
        @open="emit('open', item)"
      />
    </div>
    <footer class="notification-panel__footer">
      <router-link class="notification-panel__all" :to="notificationsRoute">
        {{ t('components.notifications.viewAll') }}
      </router-link>
    </footer>
  </div>
</template>
