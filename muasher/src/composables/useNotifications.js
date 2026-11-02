import { onMounted, ref } from 'vue'
import { notificationsService } from '@/services/notificationsService'

export function useNotifications({ auto = true, perPage = 8 } = {}) {
  const items = ref([])
  const unreadCount = ref(0)
  const status = ref('loading')

  async function load() {
    status.value = 'loading'
    try {
      const [listEnvelope, countEnvelope] = await Promise.all([
        notificationsService.list({ perPage }),
        notificationsService.unreadCount(),
      ])
      items.value = listEnvelope.data ?? []
      unreadCount.value = countEnvelope.data?.count ?? 0
      status.value = items.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  async function markRead(id) {
    const item = items.value.find((candidate) => candidate.id === id)
    if (!item || item.read) return
    await notificationsService.markRead(id)
    item.read = true
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }

  async function markAllRead() {
    await notificationsService.markAllRead()
    items.value = items.value.map((item) => ({ ...item, read: true }))
    unreadCount.value = 0
  }

  if (auto) onMounted(load)

  return { items, unreadCount, status, load, markRead, markAllRead }
}
