import { describe, expect, it, vi, beforeEach } from 'vitest'
import { aiAssistantService } from '@/services/aiAssistantService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

beforeEach(() => {
  vi.clearAllMocks()
})

describe('aiAssistantService', () => {
  it('reads assistant availability and unwraps the envelope', async () => {
    apiClient.get.mockResolvedValue({ data: { data: { active: true }, meta: {}, errors: null } })
    const result = await aiAssistantService.getAssistant()
    expect(apiClient.get).toHaveBeenCalledWith('/ai/assistant')
    expect(result.data).toEqual({ active: true })
  })

  it('posts a query and logs only the payload SHAPE (never the question text)', async () => {
    apiClient.post.mockResolvedValue({ data: { data: { matched: false }, meta: {}, errors: null } })
    await aiAssistantService.query('كم رسوم المسار؟')
    expect(apiClient.post).toHaveBeenCalledWith('/ai/query', { question: 'كم رسوم المسار؟' })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'ai.query',
      expect.objectContaining({ entity: 'ai-query', payloadShape: ['question'] }),
    )
  })

  it('reports failures to Sentry and rethrows (never swallows)', async () => {
    const failure = new Error('network')
    apiClient.post.mockRejectedValue(failure)
    await expect(aiAssistantService.query('x')).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'ai.query' }),
    )
  })
})
