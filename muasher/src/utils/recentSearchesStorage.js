import { STORAGE_KEYS } from '@/constants/storageKeys'
import { logger } from '@/utils/logger'

const KEY_PREFIX = `${STORAGE_KEYS.recentSearches}.`

export function recentSearchesKey(userId) {
  return `${KEY_PREFIX}${userId}`
}

function storedRecentSearchKeys() {
  return Array.from({ length: localStorage.length }, (_, index) => localStorage.key(index)).filter(
    (key) => key?.startsWith(KEY_PREFIX),
  )
}

export function forgetRecentSearches() {
  try {
    storedRecentSearchKeys().forEach((key) => localStorage.removeItem(key))
  } catch (error) {
    logger.error(error, { action: 'search.recent.forget' })
  }
}
