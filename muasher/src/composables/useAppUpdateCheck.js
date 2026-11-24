import { onBeforeUnmount, onMounted, ref } from 'vue'
import {
  APP_UPDATE_INDEX_URL,
  APP_UPDATE_MIN_GAP_MS,
  APP_UPDATE_POLL_MS,
} from '@/constants/appUpdate'
import { bootBundleSignature, bundleSignatureOfHtml } from '@/utils/bundleSignature'
import { logger } from '@/utils/logger'

// Tells a tab still running an older bundle that a newer build is live, so the user can reload
// instead of working on screens (and rules) the server has moved past.
export function useAppUpdateCheck({ fetcher = (...args) => fetch(...args), now = Date.now } = {}) {
  const updateAvailable = ref(false)
  const current = bootBundleSignature()
  let lastCheck = 0
  let timer = null

  async function check() {
    if (!current || updateAvailable.value) return
    lastCheck = now()
    try {
      const response = await fetcher(APP_UPDATE_INDEX_URL, { cache: 'no-store' })
      if (!response.ok) return
      const latest = bundleSignatureOfHtml(await response.text())
      if (latest && latest !== current) updateAvailable.value = true
    } catch (error) {
      // Offline, or a deploy in progress: the next check tries again.
      logger.breadcrumb('app-update', 'check.failed', { message: error?.message })
    }
  }

  function checkWhenSeen() {
    if (document.visibilityState === 'visible' && now() - lastCheck >= APP_UPDATE_MIN_GAP_MS) {
      check()
    }
  }

  onMounted(() => {
    if (!current) return
    timer = setInterval(check, APP_UPDATE_POLL_MS)
    window.addEventListener('focus', checkWhenSeen)
    document.addEventListener('visibilitychange', checkWhenSeen)
  })

  onBeforeUnmount(() => {
    clearInterval(timer)
    window.removeEventListener('focus', checkWhenSeen)
    document.removeEventListener('visibilitychange', checkWhenSeen)
  })

  return { updateAvailable, check }
}
