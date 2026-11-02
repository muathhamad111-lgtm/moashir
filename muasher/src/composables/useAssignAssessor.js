import { onBeforeUnmount, ref, watch } from 'vue'
import { requestsService } from '@/services/requestsService'
import { SEARCH_DEBOUNCE_MS } from '@/constants/globalSearch'

export function useAssignAssessor({ requestId }) {
  const assessors = ref([])
  const status = ref('idle')
  const search = ref('')
  let timer = null

  async function loadAssessors() {
    status.value = 'loading'
    try {
      const params = { assessmentId: requestId.value, ...(search.value.trim() ? { search: search.value.trim() } : {}) }
      const { data } = await requestsService.listAssessors(params)
      assessors.value = data
      status.value = data.length ? 'ready' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(loadAssessors, SEARCH_DEBOUNCE_MS)
  })

  function submit(mode, payload) {
    return mode === 'reassign' ? requestsService.reassign(requestId.value, payload) : requestsService.assign(requestId.value, payload)
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { assessors, status, search, loadAssessors, submit }
}
