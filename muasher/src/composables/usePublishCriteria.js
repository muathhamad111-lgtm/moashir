import { ref } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { CRITERIA_ERROR_CODES } from '@/constants/criteria'

export function usePublishCriteria() {
  const submitting = ref(false)
  const problems = ref([])
  const fieldErrors = ref({})
  const errorMessage = ref('')

  function reset() {
    problems.value = []
    fieldErrors.value = {}
    errorMessage.value = ''
  }

  function absorb(error) {
    const normalized = error?.normalized ?? {}
    errorMessage.value = normalized.message ?? ''
    fieldErrors.value = normalized.fieldErrors ?? {}
    problems.value =
      normalized.code === CRITERIA_ERROR_CODES.WEIGHTS_INCONSISTENT
        ? (normalized.meta?.problems ?? [])
        : []
  }

  async function publish(version, instruction) {
    if (submitting.value) return null
    submitting.value = true
    reset()
    try {
      return await criteriaService.publishVersion(version.id, instruction)
    } catch (error) {
      absorb(error)
      return null
    } finally {
      submitting.value = false
    }
  }

  return { submitting, problems, fieldErrors, errorMessage, publish, reset }
}

export function useConsistencyCheck() {
  const checking = ref(false)
  const checked = ref(false)
  const problems = ref([])

  async function check(versionId) {
    checking.value = true
    try {
      const { data } = await criteriaService.checkVersion(versionId)
      problems.value = data?.problems ?? []
      checked.value = true
      return data
    } finally {
      checking.value = false
    }
  }

  function clear() {
    problems.value = []
    checked.value = false
  }

  return { checking, checked, problems, check, clear }
}
