import { computed, ref, shallowRef } from 'vue'
import { submissionService } from '@/services/submissionService'
import { ACCREDITED_STATUSES, ENTITY_EDITABLE_STATUSES } from '@/constants/accredited'
import { isRequested } from '@/utils/accredited/evidenceRules'

const NOT_FOUND = 404
const FORBIDDEN = 403

function stateOfFailure(error) {
  const status = error?.normalized?.status
  if (status === NOT_FOUND) return 'missing'
  if (status === FORBIDDEN) return 'blocked'
  return 'error'
}

export function useAccreditedForm(assessmentId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const form = shallowRef(null)
  const lockedCode = ref('')

  const assessment = computed(() => form.value?.assessment ?? null)
  const completionRequest = computed(() => form.value?.completionRequest ?? null)
  const isResubmission = computed(() => assessment.value?.status === ACCREDITED_STATUSES.NEEDS_COMPLETION)
  const isEditable = computed(
    () => Boolean(assessment.value) && ENTITY_EDITABLE_STATUSES.includes(assessment.value.status) && !assessment.value.isFrozen && !lockedCode.value,
  )

  function canEdit(subId, kind) {
    if (!isEditable.value) return false
    return !isResubmission.value || isRequested(completionRequest.value, subId, kind)
  }

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await submissionService.getForm(assessmentId.value)
      form.value = data
      status.value = 'ready'
    } catch (error) {
      if (silent) return
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = stateOfFailure(error)
    }
  }

  function patch(changes) {
    form.value = { ...form.value, ...changes }
  }

  function patchAssessment(changes) {
    patch({ assessment: { ...form.value.assessment, ...changes } })
  }

  function lock(code) {
    lockedCode.value = code
  }

  return { status, errorMessage, form, assessment, completionRequest, isResubmission, isEditable, lockedCode, canEdit, load, patch, patchAssessment, lock }
}
