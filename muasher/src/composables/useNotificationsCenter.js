import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { notificationsService } from '@/services/notificationsService'
import { NOTIFICATION_FILTERS } from '@/constants/notifications'

export function useNotificationsCenter({ perPage = 8 } = {}) {
  const { t, locale } = useI18n()
  const auth = useAuthStore()

  const activeFilter = ref('all')
  const search = ref('')
  const page = ref(1)

  const items = ref([])
  const status = ref('loading')
  const errorMessage = ref('')
  const unreadCount = ref(0)
  const pagination = ref({ page: 1, perPage, total: 0, lastPage: 1 })
  const busyIds = ref([])
  const markingAll = ref(false)

  const recipientRole = computed(() => auth.roles?.[0] ?? '')

  const filterTabs = computed(() =>
    NOTIFICATION_FILTERS.map((value) => ({
      value,
      label: t(`notifications.filters.${value}`),
    })),
  )

  function localized(content) {
    if (!content) return ''
    return content[locale.value] || content.ar || content.en || ''
  }

  function buildParams() {
    const params = { page: page.value, perPage }
    if (recipientRole.value) params['filter[recipientRole]'] = recipientRole.value
    if (activeFilter.value === 'unread') params['filter[read]'] = false
    else if (activeFilter.value !== 'all') params['filter[type]'] = activeFilter.value
    if (search.value.trim()) params.search = search.value.trim()
    return params
  }

  async function refreshUnread() {
    const params = recipientRole.value ? { 'filter[recipientRole]': recipientRole.value } : {}
    const envelope = await notificationsService.unreadCount(params).catch(() => null)
    if (envelope) unreadCount.value = envelope.data?.count ?? 0
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await notificationsService.list(buildParams())
      items.value = envelope.data ?? []
      const meta = envelope.meta?.pagination
      if (meta) {
        pagination.value = {
          page: meta.page ?? page.value,
          perPage: meta.perPage ?? perPage,
          total: meta.total ?? 0,
          lastPage: meta.lastPage ?? 1,
        }
      }
      status.value = items.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
    await refreshUnread()
  }

  function setFilter(value) {
    if (activeFilter.value === value) return
    activeFilter.value = value
    page.value = 1
  }

  function setPage(next) {
    if (next < 1 || next > pagination.value.lastPage || next === page.value) return
    page.value = next
  }

  async function markRead(id) {
    const item = items.value.find((candidate) => candidate.id === id)
    if (!item || item.read || busyIds.value.includes(id)) return
    busyIds.value = [...busyIds.value, id]
    try {
      await notificationsService.markRead(id)
      item.read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
      if (activeFilter.value === 'unread') await load()
    } finally {
      busyIds.value = busyIds.value.filter((candidate) => candidate !== id)
    }
  }

  async function markAllRead() {
    if (markingAll.value || unreadCount.value === 0) return
    markingAll.value = true
    try {
      const payload = recipientRole.value ? { recipientRole: recipientRole.value } : {}
      await notificationsService.markAllRead(payload)
      items.value = items.value.map((item) => ({ ...item, read: true }))
      unreadCount.value = 0
      if (activeFilter.value === 'unread') await load()
    } finally {
      markingAll.value = false
    }
  }

  watch(search, () => {
    page.value = 1
  })
  watch([activeFilter, search, page], () => load())
  onMounted(load)

  return {
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
    localized,
    load,
    setFilter,
    setPage,
    markRead,
    markAllRead,
  }
}
