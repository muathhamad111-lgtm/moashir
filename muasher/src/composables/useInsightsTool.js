import { ref, unref, watch } from 'vue'
import { INSIGHTS_ERROR_CODES } from '@/constants/insights'

const NOT_FOUND = 404
const CONFLICT = 409

export function useInsightsTool(loader, assessmentId) {
  const status = ref('idle')
  const data = ref(null)
  const reason = ref('')

  function fail(error) {
    const normalized = error?.normalized ?? {}
    if (normalized.status === CONFLICT && normalized.code === INSIGHTS_ERROR_CODES.NOT_ELIGIBLE) {
      reason.value = normalized.meta?.reason ?? ''
      return 'ineligible'
    }
    return normalized.status === NOT_FOUND ? 'missing' : 'error'
  }

  async function load() {
    const id = unref(assessmentId)
    if (!id) {
      status.value = 'idle'
      return
    }
    status.value = 'loading'
    reason.value = ''
    try {
      data.value = (await loader(id)).data
      status.value = 'ready'
    } catch (error) {
      data.value = null
      status.value = fail(error)
    }
  }

  watch(() => unref(assessmentId), load, { immediate: true })

  return { status, data, reason, load }
}
