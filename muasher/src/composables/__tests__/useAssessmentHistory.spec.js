import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAssessmentHistory } from '@/composables/useAssessmentHistory'
import { assessmentsService } from '@/services/assessmentsService'

vi.mock('@/services/assessmentsService', () => ({
  assessmentsService: { list: vi.fn() },
}))

const entries = [
  { id: 9002, path: 'self', status: 'draft', result: null, createdAt: '2026-10-28T11:15:00Z' },
  { id: 9001, path: 'self', status: 'result-available', result: { percent: '72.40' }, createdAt: '2026-06-20T07:00:00Z' },
]

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useAssessmentHistory', () => {
  it('loads the newest assessments first with pagination params and applies the meta', async () => {
    assessmentsService.list.mockResolvedValue({ data: entries, meta: { pagination: { page: 1, perPage: 10, total: 12, lastPage: 2 } } })
    const api = useAssessmentHistory()
    await api.load()
    expect(assessmentsService.list).toHaveBeenCalledWith({ sort: '-created_at', page: 1, perPage: 10 })
    expect(api.status.value).toBe('success')
    expect(api.items.value).toHaveLength(2)
    expect(api.pagination).toMatchObject({ total: 12, lastPage: 2 })
  })

  it('moves to another page', async () => {
    assessmentsService.list.mockResolvedValue({ data: entries, meta: { pagination: { page: 2, perPage: 10, total: 12, lastPage: 2 } } })
    const api = useAssessmentHistory()
    await api.load()
    await api.goToPage(2)
    expect(assessmentsService.list).toHaveBeenLastCalledWith({ sort: '-created_at', page: 2, perPage: 10 })
  })

  it('reports empty when there are no assessments', async () => {
    assessmentsService.list.mockResolvedValue({ data: [], meta: {} })
    const api = useAssessmentHistory()
    await api.load()
    expect(api.status.value).toBe('empty')
  })

  it('reports the error state with the translated message', async () => {
    assessmentsService.list.mockRejectedValue({ normalized: { message: 'تعذّر التحميل' } })
    const api = useAssessmentHistory()
    await api.load()
    expect(api.status.value).toBe('error')
    expect(api.errorMessage.value).toBe('تعذّر التحميل')
  })
})
