import { describe, expect, it, vi, beforeEach } from 'vitest'
import { feedbackReportService } from '@/services/feedbackReportService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }

beforeEach(() => {
  vi.clearAllMocks()
})

describe('feedbackReportService', () => {
  it('gets a report by assessment id', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await feedbackReportService.get(9102)
    expect(apiClient.get).toHaveBeenCalledWith('/assessments/9102/feedback-report')
    const breadcrumb = logger.breadcrumb.mock.calls.find((c) => c[1] === 'feedbackReport.get')
    expect(breadcrumb[2].entity).toBe('feedback-report')
  })

  it('exports a report', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await feedbackReportService.export(9102)
    expect(apiClient.post).toHaveBeenCalledWith('/assessments/9102/feedback-report/export', {})
  })

  it('reports failures to Sentry and rethrows', async () => {
    const failure = new Error('report failed')
    apiClient.get.mockRejectedValue(failure)
    await expect(feedbackReportService.get(9102)).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'feedbackReport.get' }),
    )
  })
})
