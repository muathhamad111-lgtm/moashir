import { describe, expect, it, vi, beforeEach } from 'vitest'
import { contentService } from '@/services/contentService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))
vi.mock('@/utils/logger', () => ({ logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() } }))

function ok(data) {
  return { data: { data, meta: {}, errors: null } }
}

beforeEach(() => vi.clearAllMocks())

describe('contentService (public CMS reads)', () => {
  it('reads the maintenance status from /content/status', async () => {
    apiClient.get.mockResolvedValue(ok({ maintenance: true, publishedAt: null }))
    const result = await contentService.getStatus()
    expect(apiClient.get).toHaveBeenCalledWith('/content/status')
    expect(result.data.maintenance).toBe(true)
  })

  it('loads the published home over /content/home', async () => {
    apiClient.get.mockResolvedValue(ok({ published: true, sections: [] }))
    await contentService.getHome()
    expect(apiClient.get).toHaveBeenCalledWith('/content/home')
    expect(logger.breadcrumb).toHaveBeenCalledWith('service', 'content.getHome', expect.objectContaining({ entity: 'home-content' }))
  })

  it('loads a published page by key and treats 404 as an expected answer', async () => {
    const notPublished = Object.assign(new Error('not published'), { response: { status: 404 } })
    apiClient.get.mockRejectedValue(notPublished)
    await expect(contentService.getPage('lenses')).rejects.toBe(notPublished)
    expect(apiClient.get).toHaveBeenCalledWith('/content/pages/lenses')
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('reports unexpected failures and rethrows', async () => {
    const failure = Object.assign(new Error('down'), { response: { status: 503 } })
    apiClient.get.mockRejectedValue(failure)
    await expect(contentService.getHome()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(failure, expect.objectContaining({ action: 'content.getHome' }))
  })

  it('loads the published model criteria', async () => {
    apiClient.get.mockResolvedValue(ok([]))
    await contentService.getModelCriteria()
    expect(apiClient.get).toHaveBeenCalledWith('/content/model-criteria')
  })
})
