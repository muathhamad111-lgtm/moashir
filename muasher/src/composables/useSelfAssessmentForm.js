import { computed, ref, shallowRef } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'
import { scorableUnits } from '@/utils/assessments/scoreSheet'
import { SELF_STATUSES } from '@/constants/assessment'

const NOT_FOUND = 404

export function useSelfAssessmentForm(assessmentId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const form = shallowRef(null)

  const assessment = computed(() => form.value?.assessment ?? null)
  const units = computed(() => scorableUnits(form.value?.criteria))
  const isDraft = computed(() => assessment.value?.status === SELF_STATUSES.DRAFT)
  const isReadOnly = computed(() => !isDraft.value || assessment.value.isFrozen)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await assessmentsService.getForm(assessmentId.value)
      form.value = data
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  function applyScores(scores) {
    form.value = { ...form.value, ...scores }
  }

  function patchAssessment(changes) {
    form.value = { ...form.value, assessment: { ...form.value.assessment, ...changes } }
  }

  return { status, errorMessage, form, assessment, units, isDraft, isReadOnly, load, applyScores, patchAssessment }
}
