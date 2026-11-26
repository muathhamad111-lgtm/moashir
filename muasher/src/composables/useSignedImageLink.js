import { ref } from 'vue'
import { ENTITY_LOGO_LINK_LIFETIME_MS } from '@/constants/entityProfile'

export function useSignedImageLink(refresh) {
  const failed = ref(false)
  let loadedAt = Date.now()
  let refreshing = false

  function markLoaded() {
    loadedAt = Date.now()
    failed.value = false
  }

  async function onImageError() {
    if (refreshing || Date.now() - loadedAt < ENTITY_LOGO_LINK_LIFETIME_MS) {
      failed.value = true
      return
    }
    refreshing = true
    try {
      await refresh()
      markLoaded()
    } catch {
      failed.value = true
    } finally {
      refreshing = false
    }
  }

  return { failed, markLoaded, onImageError }
}
