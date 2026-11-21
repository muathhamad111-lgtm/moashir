import { computed, ref, toValue, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'

export const DISMISSED_NOTICES_KEY = 'muasher.dismissedNotices'

// Only the latest hidden notices are kept, so the stored list never grows without bound.
const MAX_REMEMBERED = 50

function storageKey(userId) {
  return `${DISMISSED_NOTICES_KEY}.${userId}`
}

function readDismissed(userId) {
  try {
    const stored = JSON.parse(localStorage.getItem(storageKey(userId)) ?? '[]')
    return Array.isArray(stored) ? stored.filter((id) => typeof id === 'string') : []
  } catch (error) {
    logger.error(error, { action: 'notices.dismissed.read' })
    return []
  }
}

function writeDismissed(userId, ids) {
  try {
    localStorage.setItem(storageKey(userId), JSON.stringify(ids))
  } catch (error) {
    logger.error(error, { action: 'notices.dismissed.write' })
  }
}

// A notice the viewer hid stays hidden for that viewer on this browser (design §B.3.6). This is a
// convenience only: when storage is unavailable the notice simply shows again.
export function useDismissedNotice(noticeKey) {
  const auth = useAuthStore()
  const userId = computed(() => auth.user?.id ?? null)
  const stored = ref([])

  watch(
    userId,
    (id) => {
      stored.value = id === null ? [] : readDismissed(id)
    },
    { immediate: true },
  )

  const dismissed = computed(() => stored.value.includes(toValue(noticeKey)))

  function update(next) {
    stored.value = next
    if (userId.value !== null) writeDismissed(userId.value, next)
  }

  function dismiss() {
    if (!dismissed.value) update([...stored.value, toValue(noticeKey)].slice(-MAX_REMEMBERED))
  }

  function restore() {
    update(stored.value.filter((id) => id !== toValue(noticeKey)))
  }

  return { dismissed, dismiss, restore }
}
