import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { STORAGE_KEYS } from '@/constants/storageKeys'
import { logger } from '@/utils/logger'

export const NOTIFICATION_LAYOUTS = Object.freeze({ byType: 'byType', byChannel: 'byChannel' })

const LAYOUTS = Object.values(NOTIFICATION_LAYOUTS)

function readStored() {
  try {
    const stored = localStorage.getItem(STORAGE_KEYS.notificationsLayout)
    return LAYOUTS.includes(stored) ? stored : null
  } catch (error) {
    logger.error(error, { action: 'notifications.layout.read' })
    return null
  }
}

function store(layout) {
  try {
    localStorage.setItem(STORAGE_KEYS.notificationsLayout, layout)
  } catch (error) {
    logger.error(error, { action: 'notifications.layout.write' })
  }
}

/**
 * Which catalogue layout to show: the link's `?view=` first, then this viewer's last choice (a browser
 * convenience only), else by notification.
 */
export function useNotificationCatalogueLayout() {
  const route = useRoute()
  const router = useRouter()
  const remembered = ref(readStored())

  return computed({
    get: () => {
      if (LAYOUTS.includes(route.query.view)) return route.query.view
      return remembered.value ?? NOTIFICATION_LAYOUTS.byType
    },
    set: (layout) => {
      remembered.value = layout
      store(layout)
      router.replace({ query: { ...route.query, view: layout } })
    },
  })
}
