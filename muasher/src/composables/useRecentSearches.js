import { ref, watch } from 'vue'
import { STORAGE_KEYS } from '@/constants/storageKeys'
import {
  PERSONAL_DATA_PATTERNS,
  RECENT_SEARCHES_LIMIT,
  SEARCH_MAX_LENGTH,
  SEARCH_MIN_LENGTH,
} from '@/constants/globalSearch'

export function isStorableSearch(text) {
  const term = typeof text === 'string' ? text.trim() : ''
  if (term.length < SEARCH_MIN_LENGTH || term.length > SEARCH_MAX_LENGTH) return false
  return !PERSONAL_DATA_PATTERNS.some((pattern) => pattern.test(term))
}

function storageKey(userId) {
  return `${STORAGE_KEYS.recentSearches}.${userId}`
}

function read(userId) {
  if (!userId) return []
  try {
    const parsed = JSON.parse(localStorage.getItem(storageKey(userId)) ?? '[]')
    return Array.isArray(parsed) ? parsed.filter(isStorableSearch).slice(0, RECENT_SEARCHES_LIMIT) : []
  } catch {
    return []
  }
}

export function useRecentSearches(userId) {
  const items = ref(read(userId.value))

  watch(userId, (next) => {
    items.value = read(next)
  })

  function persist(next) {
    items.value = next
    localStorage.setItem(storageKey(userId.value), JSON.stringify(next))
  }

  function remember(text) {
    if (!userId.value || !isStorableSearch(text)) return
    const term = text.trim()
    const others = items.value.filter((item) => item.toLocaleLowerCase() !== term.toLocaleLowerCase())
    persist([term, ...others].slice(0, RECENT_SEARCHES_LIMIT))
  }

  function clear() {
    if (!userId.value) return
    items.value = []
    localStorage.removeItem(storageKey(userId.value))
  }

  return { items, remember, clear }
}
