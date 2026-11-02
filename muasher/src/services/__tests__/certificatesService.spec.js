import { describe, expect, it, vi, beforeEach } from 'vitest'
import { certificatesService } from '@/services/certificatesService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: [], meta: {}, errors: null }

beforeEach(() => {
  vi.clearAllMocks()
})

describe('certificatesService', () => {
  it('lists certificates with a filter[kind] params shape', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await certificatesService.list({ 'filter[kind]': 'accredited' })
    expect(apiClient.get).toHaveBeenCalledWith('/certificates', {
      params: { 'filter[kind]': 'accredited' },
    })
    const breadcrumb = logger.breadcrumb.mock.calls.find((c) => c[1] === 'certificates.list')
    expect(breadcrumb[2].paramsShape).toEqual(['filter[kind]'])
  })

  it('gets a certificate by id', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await certificatesService.get('cert-1')
    expect(apiClient.get).toHaveBeenCalledWith('/certificates/cert-1')
  })

  it('requests a download', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await certificatesService.download('cert-1')
    expect(apiClient.post).toHaveBeenCalledWith('/certificates/cert-1/download')
  })

  it('reports failures to Sentry and rethrows', async () => {
    const failure = new Error('list failed')
    apiClient.get.mockRejectedValue(failure)
    await expect(certificatesService.list()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'certificates.list' }),
    )
  })
})
