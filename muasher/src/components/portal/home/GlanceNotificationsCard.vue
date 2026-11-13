<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'
import { isInternalPath } from '@/utils/internalPath'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { JOURNEY_GLANCE_NOTIFICATIONS } from '@/constants/portal'
import NotificationItem from '@/components/notifications/NotificationItem.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import GlanceCard from './GlanceCard.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const router = useRouter()
const { items, status, load, markRead } = useNotifications({
  perPage: JOURNEY_GLANCE_NOTIFICATIONS,
})

async function open(item) {
  await markRead(item.id).catch(() => null)
  if (isInternalPath(item.link)) router.push(item.link)
}
</script>

<template>
  <GlanceCard
    class="glance-notifications"
    :title="t('portal.home.glance.notifications.title')"
    icon="envelope"
    heading-id="glance-notifications-title"
  >
    <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
    <ErrorState v-else-if="status === 'error'" @retry="load" />
    <p v-else-if="status === 'empty'" class="glance-card__empty">
      {{ t('portal.home.glance.notifications.empty') }}
    </p>
    <ul v-else class="glance-notifications__list">
      <li v-for="item in items" :key="item.id">
        <NotificationItem :notification="item" :show-body="false" @open="open" />
      </li>
    </ul>
    <template #footer>
      <router-link class="glance-card__link" :to="{ name: ROUTE_NAMES.NOTIFICATIONS_INDEX }">
        <span>{{ t('portal.home.glance.notifications.link') }}</span>
        <AppIcon name="arrow-right" />
      </router-link>
    </template>
  </GlanceCard>
</template>
