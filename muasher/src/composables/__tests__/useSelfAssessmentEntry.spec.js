import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ENTRY_STATES, useSelfAssessmentEntry } from '@/composables/useSelfAssessmentEntry'
import { assessmentsService } from '@/services/assessmentsService'
import { toast } from '@/composables/useToast'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: { list: vi.fn(), startSelf: vi.fn() },
}))

function rejection(status, code, meta = {}) {
  return Object.assign(new Error(code), {
    normalized: { status, code, meta, message: `message ${code}`, fieldErrors: {} },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.spyOn(toast, 'info')
  assessmentsService.list.mockResolvedValue({ data: [], meta: {} })
})

describe('useSelfAssessmentEntry', () => {
  it('resumes the single open draft found by status and path', async () => {
    assessmentsService.list.mockResolvedValue({ data: [{ id: 9002 }], meta: {} })
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.locate()
    expect(assessmentsService.list).toHaveBeenCalledWith({
      'filter[path]': 'self',
      'filter[status]': 'draft',
      perPage: 1,
    })
    expect(api.state.value).toBe(ENTRY_STATES.DRAFT)
    expect(api.draftId.value).toBe(9002)
    expect(assessmentsService.startSelf).not.toHaveBeenCalled()
  })

  it('starts a draft at once when there is none and opens it', async () => {
    assessmentsService.startSelf.mockResolvedValue({
      data: { id: 9101, number: 'SLF-2026-000006' },
    })
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.locate()
    expect(assessmentsService.startSelf).toHaveBeenCalledTimes(1)
    expect(api.state.value).toBe(ENTRY_STATES.DRAFT)
    expect(api.draftId.value).toBe(9101)
  })

  it('shows the no-criteria state when nothing is published, and the error state otherwise', async () => {
    assessmentsService.startSelf.mockRejectedValue(rejection(404, 'criteria.no_published_version'))
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.locate()
    expect(api.state.value).toBe(ENTRY_STATES.NO_CRITERIA)
    assessmentsService.list.mockRejectedValue(rejection(503, 'service_unavailable'))
    await api.locate()
    expect(api.state.value).toBe(ENTRY_STATES.ERROR)
    expect(api.errorMessage.value).toBe('message service_unavailable')
  })

  it('opens the existing draft on 409 self.draft_exists', async () => {
    assessmentsService.startSelf.mockRejectedValue(
      rejection(409, 'self.draft_exists', { assessmentId: 9002 }),
    )
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.locate()
    expect(api.draftId.value).toBe(9002)
    expect(toast.info).toHaveBeenCalled()
  })

  it('blocks an inactive or frozen entity and shows unknown failures as an error state', async () => {
    assessmentsService.startSelf.mockRejectedValueOnce(
      rejection(403, 'assessment.entity_not_active'),
    )
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.locate()
    expect(api.state.value).toBe(ENTRY_STATES.BLOCKED)
    expect(api.blockedCode.value).toBe('assessment.entity_not_active')
    assessmentsService.startSelf.mockRejectedValueOnce(rejection(429, 'rate_limited'))
    await api.locate()
    expect(api.state.value).toBe(ENTRY_STATES.ERROR)
    expect(api.errorMessage.value).toBe('message rate_limited')
  })
})
