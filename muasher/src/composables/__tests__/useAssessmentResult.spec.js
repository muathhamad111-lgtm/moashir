import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useAssessmentResult } from '@/composables/useAssessmentResult'
import { useAssessmentResultHandoffStore } from '@/stores/assessmentResultHandoff'
import { assessmentsService } from '@/services/assessmentsService'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: { getResult: vi.fn() },
}))

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useAssessmentResult', () => {
  it('shows the result handed over by the submission without another request', async () => {
    const { api } = withSetup(() => useAssessmentResult(ref(9002)))
    useAssessmentResultHandoffStore().hand({ id: 9002, available: true })
    await api.init()
    expect(api.status.value).toBe('ready')
    expect(api.result.value).toEqual({ id: 9002, available: true })
    expect(assessmentsService.getResult).not.toHaveBeenCalled()
  })

  it('ignores a handed result of another assessment and loads from the API', async () => {
    assessmentsService.getResult.mockResolvedValue({
      data: { id: 9001, available: false, status: 'draft' },
    })
    const { api } = withSetup(() => useAssessmentResult(ref(9001)))
    useAssessmentResultHandoffStore().hand({ id: 9002, available: true })
    await api.init()
    expect(assessmentsService.getResult).toHaveBeenCalledWith(9001)
    expect(api.result.value.available).toBe(false)
  })

  it('reports missing on 404 and error otherwise', async () => {
    assessmentsService.getResult.mockRejectedValueOnce({
      normalized: { status: 404, message: 'Not found' },
    })
    const { api } = withSetup(() => useAssessmentResult(ref(1)))
    await api.load()
    expect(api.status.value).toBe('missing')
    assessmentsService.getResult.mockRejectedValueOnce({
      normalized: { status: 503, message: 'Unavailable' },
    })
    await api.load()
    expect(api.status.value).toBe('error')
    expect(api.errorMessage.value).toBe('Unavailable')
  })
})
