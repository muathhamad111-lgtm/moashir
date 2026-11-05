import { onBeforeUnmount, ref, watch } from 'vue'
import { requestsService } from '@/services/requestsService'
import { SEARCH_DEBOUNCE_MS } from '@/constants/globalSearch'

export const ASSIGNMENT_CODES = Object.freeze({ NOT_QUALIFIED: 'assessor.not_qualified' })

const NOT_FOUND = 404

function isOutOfScope(error) {
  const normalized = error?.normalized ?? {}
  return normalized.status === NOT_FOUND || Boolean(normalized.fieldErrors?.assessmentId)
}

export function useAssignAssessor({ requestId }) {
  const assessors = ref([])
  const status = ref('idle')
  const search = ref('')
  let timer = null

  async function loadAssessors() {
    status.value = 'loading'
    try {
      const params = {
        assessmentId: requestId.value,
        ...(search.value.trim() ? { search: search.value.trim() } : {}),
      }
      const { data } = await requestsService.listAssessors(params)
      assessors.value = data
      status.value = data.length ? 'ready' : 'empty'
    } catch (error) {
      assessors.value = []
      status.value = isOutOfScope(error) ? 'outOfScope' : 'error'
    }
  }

  watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(loadAssessors, SEARCH_DEBOUNCE_MS)
  })

  function submit(mode, payload) {
    return mode === 'reassign'
      ? requestsService.reassign(requestId.value, payload)
      : requestsService.assign(requestId.value, payload)
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { assessors, status, search, loadAssessors, submit }
}

export function assignmentFailureOf(error) {
  const normalized = error?.normalized ?? {}
  return {
    notQualified: normalized.code === ASSIGNMENT_CODES.NOT_QUALIFIED,
    outOfScope: normalized.status === NOT_FOUND,
    requiredPath: normalized.meta?.path ?? null,
    fields: normalized.fieldErrors ?? {},
    message: normalized.message ?? '',
  }
}
