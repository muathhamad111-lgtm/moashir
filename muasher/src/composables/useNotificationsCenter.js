import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { notificationsService } from '@/services/notificationsService'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { groupNotificationsByDay } from '@/utils/notificationGroups'
import { NOTIFICATION_TABS } from '@/constants/notifications'

const DEFAULT_PER_PAGE = 15

function queryOf(tab, page, perPage) {
  const params = { page, perPage }
  if (tab === NOTIFICATION_TABS.UNREAD) params['filter[read]'] = 'false'
  return params
}

function toPagination(meta, fallbackPage, perPage) {
  return {
    page: meta?.page ?? fallbackPage,
    perPage: meta?.perPage ?? perPage,
    total: meta?.total ?? 0,
    lastPage: meta?.lastPage ?? 1,
  }
}

export function useNotificationsCenter({
  initialTab = NOTIFICATION_TABS.ALL,
  perPage = DEFAULT_PER_PAGE,
} = {}) {
  const { t } = useI18n()
  const counts = usePortalCountsStore()
  const tab = ref(
    Object.values(NOTIFICATION_TABS).includes(initialTab) ? initialTab : NOTIFICATION_TABS.ALL,
  )
  const page = ref(1)
  const items = ref([])
  const status = ref('loading')
  const errorMessage = ref('')
  const totalAll = ref(null)
  const pagination = ref(toPagination(null, 1, perPage))
  const busyIds = ref([])
  const markingAll = ref(false)

  const unreadCount = computed(() => counts.unreadNotifications)
  const groups = computed(() => groupNotificationsByDay(items.value))
  const tabs = computed(() => [
    {
      value: NOTIFICATION_TABS.ALL,
      label: t('notifications.center.tabs.all'),
      count: totalAll.value,
    },
    {
      value: NOTIFICATION_TABS.UNREAD,
      label: t('notifications.center.tabs.unread'),
      count: unreadCount.value,
    },
  ])

  function applyEnvelope(envelope) {
    items.value = envelope.data
    pagination.value = toPagination(envelope.meta?.pagination, page.value, perPage)
    counts.setUnreadNotifications(envelope.meta?.unreadCount ?? 0)
    if (tab.value === NOTIFICATION_TABS.ALL) totalAll.value = pagination.value.total
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      applyEnvelope(await notificationsService.list(queryOf(tab.value, page.value, perPage)))
      status.value = items.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function setTab(value) {
    if (tab.value === value) return
    tab.value = value
    page.value = 1
  }

  function setPage(next) {
    if (next < 1 || next > pagination.value.lastPage || next === page.value) return
    page.value = next
  }

  async function withBusy(id, action) {
    if (busyIds.value.includes(id)) return
    busyIds.value = [...busyIds.value, id]
    try {
      await action()
    } finally {
      busyIds.value = busyIds.value.filter((candidate) => candidate !== id)
    }
  }

  function markRead(item) {
    if (item.isRead) return Promise.resolve()
    return withBusy(item.id, async () => {
      await notificationsService.markRead(item.id)
      item.isRead = true
      counts.notificationRead()
    })
  }

  function archive(item) {
    return withBusy(item.id, async () => {
      await notificationsService.archive(item.id)
      if (!item.isRead) counts.notificationRead()
      items.value = items.value.filter((candidate) => candidate.id !== item.id)
      if (totalAll.value) totalAll.value -= 1
      if (!items.value.length) await load()
    })
  }

  async function markAllRead() {
    if (markingAll.value || unreadCount.value === 0) return
    markingAll.value = true
    try {
      await notificationsService.markAllRead()
      counts.setUnreadNotifications(0)
      items.value = items.value.map((item) => ({ ...item, isRead: true }))
      if (tab.value === NOTIFICATION_TABS.UNREAD) await load()
    } finally {
      markingAll.value = false
    }
  }

  watch([tab, page], load)
  onMounted(load)

  return {
    tab,
    tabs,
    items,
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
  }
}
