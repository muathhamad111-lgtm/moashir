import { computed, ref, shallowRef } from 'vue'
import { assessorReviewService } from '@/services/assessorReviewService'
import { REVIEW_MODES } from '@/constants/accredited'
import { isAdvancedPath, stagesFor } from '@/utils/assessments/reviewStages'
import { scorableUnits } from '@/utils/assessments/scoreSheet'

const NOT_FOUND = 404
const FORBIDDEN = 403

export function useAssessorReview(assessmentId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const review = shallowRef(null)

  const assessment = computed(() => review.value?.assessment ?? null)
  const units = computed(() => scorableUnits(review.value?.criteria))
  const isEditMode = computed(
    () => review.value?.mode === REVIEW_MODES.EDIT && !assessment.value?.isFrozen,
  )
  const stages = computed(() => stagesFor(assessment.value?.path))
  const canScore = computed(
    () => isEditMode.value && assessment.value?.status === stages.value.reviewing,
  )
  const canStart = computed(
    () => isEditMode.value && assessment.value?.status === stages.value.assigned,
  )
  const canRequestCompletion = computed(
    () => canScore.value && !isAdvancedPath(assessment.value?.path),
  )

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await assessorReviewService.getReview(assessmentId.value)
      review.value = data
      status.value = 'ready'
    } catch (error) {
      if (silent) return
      errorMessage.value = error?.normalized?.message ?? ''
      const code = error?.normalized?.status
      status.value = code === NOT_FOUND ? 'missing' : code === FORBIDDEN ? 'blocked' : 'error'
    }
  }

  function patch(changes) {
    review.value = { ...review.value, ...changes }
  }

  function patchAssessment(changes) {
    patch({ assessment: { ...review.value.assessment, ...changes } })
  }

  return {
    status,
    errorMessage,
    review,
    assessment,
    units,
    isEditMode,
    canScore,
    canStart,
    canRequestCompletion,
    load,
    patch,
    patchAssessment,
  }
}
