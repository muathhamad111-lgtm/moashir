import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { notificationsService } from '@/services/notificationsService'
import { usePortalCountsStore } from '@/stores/portalCounts'

export function useNotifications({ auto = true, perPage = 8 } = {}) {
  const counts = usePortalCountsStore()
  const { unreadNotifications } = storeToRefs(counts)
  const items = ref([])
  const status = ref('loading')

  async function load() {
    status.value = 'loading'
    try {
      const envelope = await notificationsService.list({ perPage })
      items.value = envelope.data
      counts.setUnreadNotifications(envelope.meta?.unreadCount ?? 0)
      status.value = items.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  async function markRead(id) {
    const item = items.value.find((candidate) => candidate.id === id)
    if (!item || item.isRead) return
    await notificationsService.markRead(id)
    item.isRead = true
    counts.notificationRead()
  }

  async function markAllRead() {
    await notificationsService.markAllRead()
    items.value = items.value.map((item) => ({ ...item, isRead: true }))
    counts.setUnreadNotifications(0)
  }

  if (auto) onMounted(load)

  return {
    items,
    unreadCount: computed(() => unreadNotifications.value),
    status,
    load,
    markRead,
    markAllRead,
  }
}
