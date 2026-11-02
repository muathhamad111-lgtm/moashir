<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Tabs from '@/components/ui/Tabs.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import NotificationCenterItem from '@/components/notifications/NotificationCenterItem.vue'
import NotificationAuditModal from '@/components/notifications/NotificationAuditModal.vue'
import { useNotificationsCenter } from '@/composables/useNotificationsCenter'

const { t } = useI18n()

const {
  activeFilter,
  filterTabs,
  search,
  items,
  status,
  errorMessage,
  unreadCount,
  pagination,
  busyIds,
  markingAll,
  load,
  setFilter,
  setPage,
  markRead,
  markAllRead,
} = useNotificationsCenter()

const auditOpen = ref(false)
const auditTarget = ref(null)

const emptyCopy = computed(() => {
  const key = activeFilter.value === 'all' ? 'all' : activeFilter.value
  return {
    title: t(`notifications.center.empty.${key}.title`),
    message: t(`notifications.center.empty.${key}.message`),
  }
})

function onFilter(value) {
  setFilter(value)
}

function openAudit(notification) {
  auditTarget.value = notification
  auditOpen.value = true
}

function isBusy(id) {
  return busyIds.value.includes(id)
}
</script>

<template>
  <section class="notifications-center">
    <header class="notifications-center__header">
      <div class="notifications-center__intro">
        <h1 class="notifications-center__title">{{ t('notifications.center.title') }}</h1>
        <p class="notifications-center__subtitle">{{ t('notifications.center.subtitle') }}</p>
      </div>
      <div class="notifications-center__header-actions">
        <span class="notifications-center__count" role="status">
          <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>
          {{ t('notifications.center.unreadCount', { count: unreadCount }) }}
        </span>
        <BaseButton
          variant="secondary"
          size="sm"
          icon="fa-solid fa-check-double"
          :disabled="unreadCount === 0 || markingAll"
          @click="markAllRead"
        >
          {{ t('notifications.center.markAllRead') }}
        </BaseButton>
      </div>
    </header>

    <div class="notifications-center__toolbar">
      <div class="notifications-center__search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input
          v-model="search"
          class="notifications-center__search-input"
          type="search"
          :placeholder="t('notifications.center.searchPlaceholder')"
          :aria-label="t('notifications.center.searchLabel')"
        />
      </div>
    </div>

    <Tabs
      :model-value="activeFilter"
      :tabs="filterTabs"
      @update:model-value="onFilter"
    >
      <div class="notifications-center__panel">
        <div v-if="status === 'loading'" class="notifications-center__loading">
          <Skeleton v-for="row in 4" :key="row" variant="block" />
        </div>

        <ErrorState
          v-else-if="status === 'error'"
          :message="errorMessage"
          @retry="load"
        />

        <EmptyState
          v-else-if="status === 'empty'"
          icon="fa-regular fa-bell-slash"
          :title="emptyCopy.title"
          :message="emptyCopy.message"
        />

        <template v-else>
          <ul class="notifications-center__list">
            <NotificationCenterItem
              v-for="item in items"
              :key="item.id"
              :notification="item"
              :busy="isBusy(item.id)"
              @mark-read="markRead"
              @audit="openAudit"
            />
          </ul>

          <Pagination
            v-if="pagination.lastPage > 1"
            class="notifications-center__pagination"
            :page="pagination.page"
            :last-page="pagination.lastPage"
            @update:page="setPage"
          />
        </template>
      </div>
    </Tabs>

    <NotificationAuditModal
      v-model="auditOpen"
      :notification="auditTarget"
    />
  </section>
</template>
