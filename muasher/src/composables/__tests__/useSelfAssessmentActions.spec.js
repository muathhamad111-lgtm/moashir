import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import { useSelfAssessmentActions } from '@/composables/useSelfAssessmentActions'
import { assessmentsService } from '@/services/assessmentsService'
import { toast } from '@/composables/useToast'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: { saveDraft: vi.fn(), submit: vi.fn() },
}))

const SHEET = { subs: { 102: '7' }, points: { 1001: '8', 1002: null } }

function rejection(status, code, fieldErrors = {}) {
  return Object.assign(new Error(code), {
    normalized: { status, code, meta: {}, message: `message ${code}`, fieldErrors },
  })
}

function setup({ valid = true, missing = [] } = {}) {
  const assessment = ref({ id: 9002, isFrozen: false, criteriaUpdateInProgress: false })
  const formState = {
    assessment: computed(() => assessment.value),
    applyScores: vi.fn(),
    patchAssessment: vi.fn((changes) => {
      assessment.value = { ...assessment.value, ...changes }
    }),
  }
  const scoreSheet = {
    isValid: vi.fn().mockResolvedValue(valid),
    payload: vi.fn(() => SHEET),
    missing: vi.fn(() => missing),
    markMissing: vi.fn(),
    applyServerErrors: vi.fn(),
  }
  const onMissing = vi.fn()
  const onSubmitted = vi.fn()
  const { api } = withSetup(() =>
    useSelfAssessmentActions({ formState, scoreSheet, onMissing, onSubmitted }),
  )
  return { api, formState, scoreSheet, onMissing, onSubmitted, assessment }
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.spyOn(toast, 'error')
  vi.spyOn(toast, 'success')
})

describe('useSelfAssessmentActions', () => {
  it('saves the complete sheet and applies the scores answered by the server', async () => {
    const answer = { assessment: { id: 9002 }, scores: {}, progress: { scored: 2, required: 3 } }
    assessmentsService.saveDraft.mockResolvedValue({ data: answer })
    const { api, formState } = setup()
    await api.save()
    expect(assessmentsService.saveDraft).toHaveBeenCalledWith(9002, { scores: SHEET })
    expect(formState.applyScores).toHaveBeenCalledWith(answer)
    expect(toast.success).toHaveBeenCalled()
    expect(api.saving.value).toBe(false)
  })

  it('does not call the API while the sheet is invalid', async () => {
    const { api } = setup({ valid: false })
    await api.save()
    await api.requestSubmit()
    expect(assessmentsService.saveDraft).not.toHaveBeenCalled()
    expect(api.confirmOpen.value).toBe(false)
    expect(toast.error).toHaveBeenCalledTimes(2)
  })

  it('reveals and marks the missing items instead of opening the confirmation', async () => {
    const missing = [{ level: 'point', id: 1002, mainId: 1 }]
    const { api, onMissing, scoreSheet } = setup({ missing })
    await api.requestSubmit()
    expect(onMissing).toHaveBeenCalledWith(missing)
    expect(scoreSheet.markMissing).toHaveBeenCalledWith(missing)
    expect(api.confirmOpen.value).toBe(false)
  })

  it('opens the confirmation for a complete sheet and hands the result over after submission', async () => {
    const result = { id: 9002, available: true, percent: '80.00' }
    assessmentsService.submit.mockResolvedValue({ data: result })
    const { api, onSubmitted } = setup()
    await api.requestSubmit()
    expect(api.confirmOpen.value).toBe(true)
    await api.confirmSubmit()
    expect(assessmentsService.submit).toHaveBeenCalledWith(9002, { scores: SHEET })
    expect(onSubmitted).toHaveBeenCalledWith(result)
    expect(api.confirmOpen.value).toBe(false)
  })

  it('turns a frozen answer into a read-only frozen draft with a conflict', async () => {
    assessmentsService.saveDraft.mockRejectedValue(rejection(409, 'entity.deleted'))
    const { api, assessment } = setup()
    await api.save()
    expect(assessment.value.isFrozen).toBe(true)
    expect(api.conflictCode.value).toBe('entity.deleted')
  })

  it('marks the criteria update hold on 409 assessment.criteria_update_in_progress', async () => {
    assessmentsService.submit.mockRejectedValue(
      rejection(409, 'assessment.criteria_update_in_progress'),
    )
    const { api, assessment } = setup()
    await api.confirmSubmit()
    expect(assessment.value.criteriaUpdateInProgress).toBe(true)
    expect(toast.error).toHaveBeenCalledWith('message assessment.criteria_update_in_progress')
  })

  it('keeps an illegal transition as a conflict and clears it on demand', async () => {
    assessmentsService.saveDraft.mockRejectedValue(rejection(409, 'state.transition_illegal'))
    const { api } = setup()
    await api.save()
    expect(api.conflictCode.value).toBe('state.transition_illegal')
    api.clearConflict()
    expect(api.conflictCode.value).toBe('')
  })

  it('applies the per-item field errors of a 422 answer', async () => {
    const fieldErrors = { 'scores.points.1002': ['The item M1.1.2 has no score yet.'] }
    assessmentsService.submit.mockRejectedValue(rejection(422, 'scores.incomplete', fieldErrors))
    const { api, scoreSheet } = setup()
    await api.confirmSubmit()
    expect(scoreSheet.applyServerErrors).toHaveBeenCalledWith(fieldErrors)
  })
})
