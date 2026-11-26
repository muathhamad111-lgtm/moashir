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

  it('treats the 409 states of a download as expected rejections', async () => {
    apiClient.post.mockRejectedValue(
      Object.assign(new Error('HTTP 409'), { response: { status: 409 } }),
    )
    await expect(certificatesService.download('cert-1')).rejects.toThrow('HTTP 409')
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('verifies a public code and maps the result', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: { result: 'under_review', certificate: { number: 'CRT-2026-000003', level: null } },
      },
    })
    const { data } = await certificatesService.verify('9F3K-MQ2T')
    expect(apiClient.get).toHaveBeenCalledWith('/verify/9F3K-MQ2T')
    expect(data).toMatchObject({ result: 'under_review', certificate: { level: null } })
  })

  it('maps the contract fields of a certificate card', async () => {
    apiClient.get.mockResolvedValue({
      data: {
        data: [
          {
            id: 'c1',
            number: 'CRT-2026-000003',
            status: 'issued',
            request: { id: 14, number: 'ACC-2026-000014' },
            entity: { name: { ar: 'جمعية', en: 'Association' } },
            verificationCode: '9F3K-MQ2T',
            verificationUrl: 'https://moashir.example.sa/verify/9F3KMQ2T',
            hasPdf: true,
          },
        ],
        meta: { statusCounts: { issued: 1 } },
      },
    })
    const { data, meta } = await certificatesService.listCards()
    expect(data[0]).toMatchObject({
      requestId: 14,
      requestNumber: 'ACC-2026-000014',
      entityName: { ar: 'جمعية', en: 'Association' },
      verificationCode: '9F3K-MQ2T',
      hasPdf: true,
    })
    expect(meta.statusCounts.issued).toBe(1)
  })
})
