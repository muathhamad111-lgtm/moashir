import { computed, ref, shallowRef } from 'vue'
import { committeeWorkService } from '@/services/committeeWorkService'
import { REVIEW_MODES } from '@/constants/accredited'
import { scorableUnits } from '@/utils/assessments/scoreSheet'
import { errorMessageOf, failureState } from '@/utils/award/loadState'

export function useCommitteeEvaluation(assignmentId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const evaluation = shallowRef(null)

  const units = computed(() => scorableUnits(evaluation.value?.criteria))
  const canScore = computed(() => evaluation.value?.mode === REVIEW_MODES.EDIT)
  const assessmentId = computed(() => evaluation.value?.assignment.assessmentId ?? null)

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    errorMessage.value = ''
    try {
      evaluation.value = (await committeeWorkService.getAssignment(assignmentId.value)).data
      status.value = 'ready'
    } catch (error) {
      if (silent) return
      errorMessage.value = errorMessageOf(error)
      status.value = failureState(error)
    }
  }

  function patch(changes) {
    evaluation.value = { ...evaluation.value, ...changes }
  }

  return { status, errorMessage, evaluation, units, canScore, assessmentId, load, patch }
}
