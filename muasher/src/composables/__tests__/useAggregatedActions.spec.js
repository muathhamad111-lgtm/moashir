import { describe, expect, it, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useAggregatedActions } from '@/composables/useAggregatedActions'
import { dashboardService } from '@/services/dashboardService'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getSummary: vi.fn() },
}))

beforeEach(() => vi.clearAllMocks())

describe('useAggregatedActions (unified Action Center — Q-42)', () => {
  it('collects and tags the actions of the other roles, skipping failed boards', async () => {
    dashboardService.getSummary.mockImplementation((key) =>
      key === 'finance'
        ? Promise.reject(new Error('offline'))
        : Promise.resolve({ data: { actions: [{ id: 'kpi-drafts', severity: 'info' }] } }),
    )
    const { actions, load } = useAggregatedActions(ref(['content-officer', 'finance']), {
      auto: false,
    })
    await load()
    expect(actions.value).toEqual([
      { id: 'content-officer:kpi-drafts', severity: 'info', source: 'content-officer' },
    ])
    expect(
      dashboardService.getSummary.mock.calls.filter(([key]) => key === 'finance'),
    ).toHaveLength(2)
  })

  it('retries a transient failure once before giving up on a board', async () => {
    dashboardService.getSummary
      .mockRejectedValueOnce(new Error('503'))
      .mockResolvedValueOnce({ data: { actions: [{ id: 'overdue' }] } })
    const { actions, load } = useAggregatedActions(ref(['finance']), { auto: false })
    await load()
    expect(actions.value).toEqual([{ id: 'finance:overdue', source: 'finance' }])
  })

  it('does not call the API when the user holds a single role', async () => {
    const { actions, load } = useAggregatedActions(ref([]), { auto: false })
    await load()
    expect(actions.value).toEqual([])
    expect(dashboardService.getSummary).not.toHaveBeenCalled()
  })
})
