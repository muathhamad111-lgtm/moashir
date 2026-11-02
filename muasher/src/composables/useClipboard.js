import { onBeforeUnmount, ref } from 'vue'
import { logger } from '@/utils/logger'

const COPIED_VISIBLE_MS = 2500

export function useClipboard() {
  const copiedKey = ref(null)
  const failedKey = ref(null)
  let timer = null

  function settle(key, copied) {
    clearTimeout(timer)
    copiedKey.value = copied ? key : null
    failedKey.value = copied ? null : key
    timer = setTimeout(() => {
      copiedKey.value = null
      failedKey.value = null
    }, COPIED_VISIBLE_MS)
  }

  async function copy(key, text) {
    try {
      await navigator.clipboard.writeText(text)
      settle(key, true)
    } catch (error) {
      logger.error(error, { action: 'clipboard.copy', key })
      settle(key, false)
    }
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { copiedKey, failedKey, copy }
}
