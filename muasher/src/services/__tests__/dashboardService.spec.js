import { describe, expect, it, vi, beforeEach } from 'vitest'
import { dashboardService } from '@/services/dashboardService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }

beforeEach(() => {
  vi.clearAllMocks()
})

describe('dashboardService', () => {
  it('requests the summary for a role and unwraps the envelope', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    const result = await dashboardService.getSummary('admin')
    expect(apiClient.get).toHaveBeenCalledWith('/dashboard/summary', {
      params: { role: 'admin' },
    })
    expect(result).toEqual(envelope)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'dashboard.getSummary',
      expect.objectContaining({ entity: 'dashboard', paramsShape: ['role'] }),
    )
  })

  it('merges extra params into the query and logs only the shape', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await dashboardService.getSummary('entity', { period: 'month' })
    expect(apiClient.get).toHaveBeenCalledWith('/dashboard/summary', {
      params: { role: 'entity', period: 'month' },
    })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'dashboard.getSummary',
      expect.objectContaining({ paramsShape: ['role', 'period'] }),
    )
  })

  it('reports failures to Sentry and rethrows (never swallows)', async () => {
    const failure = new Error('boom')
    apiClient.get.mockRejectedValue(failure)
    await expect(dashboardService.getSummary('follower')).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'dashboard.getSummary' }),
    )
  })
})
