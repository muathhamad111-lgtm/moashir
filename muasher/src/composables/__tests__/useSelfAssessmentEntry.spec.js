import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ENTRY_STATES, useSelfAssessmentEntry } from '@/composables/useSelfAssessmentEntry'
import { assessmentsService } from '@/services/assessmentsService'
import { toast } from '@/composables/useToast'
import { withSetup } from '@/test-utils/withSetup'

vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: { list: vi.fn(), getPublishedCriteria: vi.fn(), startSelf: vi.fn() },
}))

function rejection(status, code, meta = {}) {
  return Object.assign(new Error(code), { normalized: { status, code, meta, message: `message ${code}`, fieldErrors: {} } })
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.spyOn(toast, 'error')
  vi.spyOn(toast, 'info')
  assessmentsService.list.mockResolvedValue({ data: [], meta: {} })
  assessmentsService.getPublishedCriteria.mockResolvedValue({ data: { versionNumber: 1, mains: [] } })
})

describe('useSelfAssessmentEntry', () => {
  it('resumes the single open draft found by status and path', async () => {
    assessmentsService.list.mockResolvedValue({ data: [{ id: 9002 }], meta: {} })
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.locate()
    expect(assessmentsService.list).toHaveBeenCalledWith({ 'filter[path]': 'self', 'filter[status]': 'draft', perPage: 1 })
    expect(api.state.value).toBe(ENTRY_STATES.DRAFT)
    expect(api.draftId.value).toBe(9002)
    expect(assessmentsService.getPublishedCriteria).not.toHaveBeenCalled()
  })

  it('offers the start with the published version when there is no draft', async () => {
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.locate()
    expect(api.state.value).toBe(ENTRY_STATES.START)
    expect(api.publishedVersion.value.versionNumber).toBe(1)
  })

  it('shows the no-criteria state when nothing is published, and the error state otherwise', async () => {
    assessmentsService.getPublishedCriteria.mockRejectedValue(rejection(404, 'criteria.no_published_version'))
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.locate()
    expect(api.state.value).toBe(ENTRY_STATES.NO_CRITERIA)
    assessmentsService.list.mockRejectedValue(rejection(503, 'service_unavailable'))
    await api.locate()
    expect(api.state.value).toBe(ENTRY_STATES.ERROR)
    expect(api.errorMessage.value).toBe('message service_unavailable')
  })

  it('starts a draft and opens it', async () => {
    assessmentsService.startSelf.mockResolvedValue({ data: { id: 9101, number: 'SLF-2026-000006' } })
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.start()
    expect(api.state.value).toBe(ENTRY_STATES.DRAFT)
    expect(api.draftId.value).toBe(9101)
  })

  it('opens the existing draft on 409 self.draft_exists', async () => {
    assessmentsService.startSelf.mockRejectedValue(rejection(409, 'self.draft_exists', { assessmentId: 9002 }))
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.start()
    expect(api.draftId.value).toBe(9002)
    expect(toast.info).toHaveBeenCalled()
  })

  it('blocks an inactive or frozen entity and keeps unknown failures as a toast', async () => {
    assessmentsService.startSelf.mockRejectedValueOnce(rejection(403, 'assessment.entity_not_active'))
    const { api } = withSetup(useSelfAssessmentEntry)
    await api.start()
    expect(api.state.value).toBe(ENTRY_STATES.BLOCKED)
    expect(api.blockedCode.value).toBe('assessment.entity_not_active')
    assessmentsService.startSelf.mockRejectedValueOnce(rejection(429, 'rate_limited'))
    await api.start()
    expect(toast.error).toHaveBeenCalledWith('message rate_limited')
  })
})
