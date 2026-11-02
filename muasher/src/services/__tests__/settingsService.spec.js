import { describe, expect, it, vi, beforeEach } from 'vitest'
import { settingsService } from '@/services/settingsService'
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

describe('settingsService', () => {
  it('gets each section from its own endpoint', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await settingsService.getGeneral()
    await settingsService.getOtp()
    expect(apiClient.get).toHaveBeenCalledWith('/settings/general')
    expect(apiClient.get).toHaveBeenCalledWith('/settings/otp')
  })

  it('updates a section via PUT and logs only the payload SHAPE (never a value)', async () => {
    apiClient.put.mockResolvedValue({ data: envelope })
    await settingsService.updateGeneral({ platformName: { ar: 'منصة مؤشر', en: 'Moashir' }, contactEmail: 'info@moashir.example.sa' })
    expect(apiClient.put).toHaveBeenCalledWith('/settings/general', {
      platformName: { ar: 'منصة مؤشر', en: 'Moashir' },
      contactEmail: 'info@moashir.example.sa',
    })
    const breadcrumb = logger.breadcrumb.mock.calls.find((call) => call[1] === 'settings.updateGeneral')
    expect(breadcrumb[2].payloadShape).toEqual(['platformName', 'contactEmail'])
    expect(JSON.stringify(breadcrumb[2])).not.toContain('example.sa')
  })

  it('updates a certificate template scoped by kind', async () => {
    apiClient.put.mockResolvedValue({ data: envelope })
    await settingsService.updateCertificate('advanced-winner', { issuanceEnabled: true })
    expect(apiClient.put).toHaveBeenCalledWith('/settings/certificates/advanced-winner', {
      issuanceEnabled: true,
    })
  })

  it('manages KB files (list/upload/approve/remove/retrain)', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    apiClient.post.mockResolvedValue({ data: envelope })
    apiClient.patch.mockResolvedValue({ data: envelope })
    apiClient.delete.mockResolvedValue({ data: envelope })
    await settingsService.listKbFiles()
    await settingsService.uploadKbFile({ name: 'f.pdf' })
    await settingsService.toggleKbFileApproved('kb-1', true)
    await settingsService.removeKbFile('kb-1')
    await settingsService.retrainKb()
    expect(apiClient.get).toHaveBeenCalledWith('/settings/ai/kb-files', { params: {} })
    expect(apiClient.post).toHaveBeenCalledWith('/settings/ai/kb-files', { name: 'f.pdf' })
    expect(apiClient.patch).toHaveBeenCalledWith('/settings/ai/kb-files/kb-1/approved', { approved: true })
    expect(apiClient.delete).toHaveBeenCalledWith('/settings/ai/kb-files/kb-1')
    expect(apiClient.post).toHaveBeenCalledWith('/settings/ai/retrain')
  })

  it('lists + updates notification types and reads the audit log', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    apiClient.put.mockResolvedValue({ data: envelope })
    await settingsService.listNotificationTypes({ 'filter[category]': 'payment' })
    await settingsService.updateNotificationType('PAY-02', { emailEnabled: false })
    await settingsService.listNotificationAudit()
    expect(apiClient.get).toHaveBeenCalledWith('/settings/notifications/types', {
      params: { 'filter[category]': 'payment' },
    })
    expect(apiClient.put).toHaveBeenCalledWith('/settings/notifications/types/PAY-02', {
      emailEnabled: false,
    })
    expect(apiClient.get).toHaveBeenCalledWith('/settings/notifications/audit', { params: {} })
  })

  it('reports failures to Sentry and rethrows (never swallows)', async () => {
    const failure = new Error('boom')
    apiClient.get.mockRejectedValue(failure)
    await expect(settingsService.getGeneral()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'settings.getGeneral' }),
    )
  })
})
