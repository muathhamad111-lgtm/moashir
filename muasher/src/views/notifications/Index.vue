<script setup>
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AreaTabs from '@/components/portal/AreaTabs.vue'
import NotificationDayGroup from '@/components/notifications/NotificationDayGroup.vue'
import NotificationPreferencesCard from '@/components/notifications/NotificationPreferencesCard.vue'
import { useNotificationsCenter } from '@/composables/useNotificationsCenter'
import { isInternalPath } from '@/utils/internalPath'

const PANEL_ID = 'notifications-panel'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const {
  tab,
  tabs,
  groups,
  status,
  errorMessage,
  unreadCount,
  pagination,
  busyIds,
  markingAll,
  load,
  setTab,
  setPage,
  markRead,
  archive,
  markAllRead,
} = useNotificationsCenter({ initialTab: route.query.tab })

function onTab(value) {
  setTab(value)
  router.replace({ query: { ...route.query, tab: value } })
}

async function open(item) {
  await markRead(item).catch(() => null)
  if (isInternalPath(item.link)) router.push(item.link)
}
</script>

<template>
  <section class="notifications-center">
    <header class="notifications-center__hero">
      <div class="notifications-center__intro">
        <p class="notifications-center__eyebrow">{{ t('notifications.center.eyebrow') }}</p>
        <h1 class="notifications-center__title">{{ t('notifications.center.title') }}</h1>
        <p class="notifications-center__subtitle">{{ t('notifications.center.subtitle') }}</p>
      </div>
      <BaseButton
        variant="secondary"
        icon="check"
        :loading="markingAll"
        :disabled="unreadCount === 0"
        @click="markAllRead"
      >
        {{ t('notifications.center.markAllRead') }}
      </BaseButton>
    </header>

    <AreaTabs
      class="notifications-center__tabs"
      :tabs="tabs"
      :model-value="tab"
      :label="t('notifications.center.tabsLabel')"
      :panel-id="PANEL_ID"
      @update:model-value="onTab"
    />

    <div class="notifications-center__body">
      <div
        :id="PANEL_ID"
        class="notifications-center__panel"
        role="tabpanel"
        :aria-labelledby="`${PANEL_ID}-tab-${tab}`"
      >
        <Skeleton v-if="status === 'loading'" variant="block" :count="4" />
        <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
        <EmptyState
          v-else-if="status === 'empty'"
          icon="bell-off"
          :title="t(`notifications.center.empty.${tab}.title`)"
          :message="t(`notifications.center.empty.${tab}.message`)"
        />
        <template v-else>
          <NotificationDayGroup
            v-for="group in groups"
            :key="group.key"
            :group="group"
            :busy-ids="busyIds"
            @open="open"
            @archive="archive"
          />
          <Pagination
            v-if="pagination.lastPage > 1"
            class="notifications-center__pagination"
            :page="pagination.page"
            :last-page="pagination.lastPage"
            @update:page="setPage"
          />
        </template>
      </div>
      <NotificationPreferencesCard class="notifications-center__preferences" />
    </div>
  </section>
</template>
