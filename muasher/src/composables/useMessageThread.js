import { onMounted, ref, unref, watch } from 'vue'
import { contactMessagesService } from '@/services/contactMessagesService'
import { usePortalCountsStore } from '@/stores/portalCounts'

const NOT_FOUND = 404

export function useMessageThread(messageId, { onChange = () => {} } = {}) {
  const counts = usePortalCountsStore()
  const thread = ref(null)
  const status = ref('idle')
  const errorMessage = ref('')

  function apply(data) {
    thread.value = data
    onChange(data)
  }

  async function markSeen() {
    if (!thread.value?.hasUnreadReply) return
    try {
      const { data } = await contactMessagesService.markSeen(thread.value.id)
      apply(data)
      await counts.refreshReplies()
    } catch {
      thread.value = { ...thread.value, hasUnreadReply: false }
    }
  }

  async function load() {
    const id = unref(messageId)
    if (!id) {
      thread.value = null
      status.value = 'idle'
      return
    }
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await contactMessagesService.getMine(id)
      thread.value = data
      status.value = 'success'
      await markSeen()
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  watch(() => unref(messageId), load)
  onMounted(load)

  return { thread, status, errorMessage, load, apply }
}
