import { ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'

export const SIDEBAR_GROUPS_KEY = 'muasher.sidebar.groups'

function keyOf(userId) {
  return `${SIDEBAR_GROUPS_KEY}.${userId}`
}

function readStored(userId) {
  try {
    const stored = JSON.parse(localStorage.getItem(keyOf(userId)) ?? 'null')
    return Array.isArray(stored) ? stored.filter((id) => typeof id === 'string') : []
  } catch (error) {
    logger.error(error, { action: 'sidebar.groups.read' })
    return []
  }
}

function writeStored(userId, ids) {
  try {
    localStorage.setItem(keyOf(userId), JSON.stringify(ids))
  } catch (error) {
    logger.error(error, { action: 'sidebar.groups.write' })
  }
}

export function useSidebarGroups(activeGroupId) {
  const auth = useAuthStore()
  const expanded = ref(new Set())

  function persist() {
    const userId = auth.user?.id
    if (userId !== null && userId !== undefined) writeStored(userId, [...expanded.value])
  }

  function open(id) {
    if (!id || expanded.value.has(id)) return
    expanded.value = new Set([...expanded.value, id])
    persist()
  }

  watch(
    () => auth.user?.id ?? null,
    (userId) => {
      expanded.value = new Set(userId === null ? [] : readStored(userId))
      open(activeGroupId.value)
    },
    { immediate: true },
  )
  watch(activeGroupId, open)

  function isExpanded(id) {
    return expanded.value.has(id)
  }

  function toggle(id) {
    const next = new Set(expanded.value)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    expanded.value = next
    persist()
  }

  return { isExpanded, toggle }
}
