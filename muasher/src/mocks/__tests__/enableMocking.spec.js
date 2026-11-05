import { beforeEach, describe, expect, it, vi } from 'vitest'

const startMockWorker = vi.fn(async () => {})

vi.mock('@/mocks/browser', () => ({ startMockWorker }))

const { enableMocking } = await import('@/mocks')
const { mockPlanOf } = await import('@/utils/mockPlan')

beforeEach(() => {
  startMockWorker.mockClear()
})

describe('enableMocking', () => {
  it('starts the whole mock layer without an API base URL', async () => {
    const plan = mockPlanOf({})
    await enableMocking(plan)
    expect(startMockWorker).toHaveBeenCalledWith(plan)
  })

  it('starts only the demo modules in hybrid mode', async () => {
    const plan = mockPlanOf({ VITE_API_BASE_URL: '/api/v1', VITE_DEMO_MODULES: 'bag' })
    await enableMocking(plan)
    expect(startMockWorker).toHaveBeenCalledWith(
      expect.objectContaining({ mode: 'hybrid', modules: ['bag'] }),
    )
  })

  it('never loads the mock worker against the real API without demo modules', async () => {
    await enableMocking(mockPlanOf({ VITE_API_BASE_URL: '/api/v1' }))
    expect(startMockWorker).not.toHaveBeenCalled()
  })
})
