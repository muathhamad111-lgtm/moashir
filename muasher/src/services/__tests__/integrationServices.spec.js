import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'
import { integrationsOverviewService } from '@/services/integrationsOverviewService'
import { paymentIntegrationService } from '@/services/paymentIntegrationService'
import { smsIntegrationService } from '@/services/smsIntegrationService'
import { emailIntegrationService } from '@/services/emailIntegrationService'
import { aiIntegrationService } from '@/services/aiIntegrationService'
import { profileChangesService } from '@/services/profileChangesService'
import { privacyRequestsService } from '@/services/privacyRequestsService'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }
const SECRET = 'sk_test_fake_example_value_0001'

beforeEach(() => {
  vi.clearAllMocks()
  for (const method of ['get', 'post', 'put', 'delete']) apiClient[method].mockResolvedValue({ data: envelope })
})

describe('integration services', () => {
  it('call one endpoint family per kind', async () => {
    await integrationsOverviewService.list()
    await paymentIntegrationService.get()
    await paymentIntegrationService.testConnection('live')
    await paymentIntegrationService.registerWebhook('test')
    await paymentIntegrationService.checkAssociationFile()
    await paymentIntegrationService.removeAssociationFile()
    await smsIntegrationService.testConnection('msegat')
    await smsIntegrationService.listTaqnyatSenders()
    await smsIntegrationService.listMsegatTemplates()
    await smsIntegrationService.sendTest({ recipient: '+966500000001', provider: 'taqnyat' })
    await emailIntegrationService.testConnection()
    await emailIntegrationService.sendTest({ recipient: 'it-team@moashir.example.sa' })
    await aiIntegrationService.listModels('anthropic')
    await aiIntegrationService.testConnection('openai')
    await aiIntegrationService.testAnswer('openai')
    expect(apiClient.get).toHaveBeenCalledWith('/settings/integrations')
    expect(apiClient.get).toHaveBeenCalledWith('/settings/payment')
    expect(apiClient.post).toHaveBeenCalledWith('/settings/payment/test-connection', { mode: 'live' })
    expect(apiClient.post).toHaveBeenCalledWith('/settings/payment/webhook/register', { mode: 'test' })
    expect(apiClient.post).toHaveBeenCalledWith('/settings/payment/apple-pay/association-file/check')
    expect(apiClient.delete).toHaveBeenCalledWith('/settings/payment/apple-pay/association-file')
    expect(apiClient.post).toHaveBeenCalledWith('/settings/sms/msegat/test-connection')
    expect(apiClient.get).toHaveBeenCalledWith('/settings/sms/taqnyat/senders')
    expect(apiClient.get).toHaveBeenCalledWith('/settings/sms/msegat/templates')
    expect(apiClient.post).toHaveBeenCalledWith('/settings/sms/test', { recipient: '+966500000001', provider: 'taqnyat' })
    expect(apiClient.post).toHaveBeenCalledWith('/settings/email/test-connection')
    expect(apiClient.post).toHaveBeenCalledWith('/settings/email/test', { recipient: 'it-team@moashir.example.sa' })
    expect(apiClient.get).toHaveBeenCalledWith('/settings/ai/models', { params: { provider: 'anthropic' } })
    expect(apiClient.post).toHaveBeenCalledWith('/settings/ai/test-connection', { provider: 'openai' })
    expect(apiClient.post).toHaveBeenCalledWith('/settings/ai/test-answer', { provider: 'openai' })
  })

  it('sends the webhook registration reason only when one is given', async () => {
    await paymentIntegrationService.registerWebhook('live', 'Key rotation after the annual review.')
    expect(apiClient.post).toHaveBeenCalledWith('/settings/payment/webhook/register', { mode: 'live', reason: 'Key rotation after the annual review.' })
  })

  it('treats a missing Apple Pay file as an expected answer', async () => {
    apiClient.delete.mockRejectedValueOnce({ response: { status: 404 } })
    await expect(paymentIntegrationService.removeAssociationFile()).rejects.toBeTruthy()
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('uploads the Apple Pay file as multipart', async () => {
    const file = new File(['association'], 'apple-developer-merchantid-domain-association', { type: 'text/plain' })
    await paymentIntegrationService.uploadAssociationFile(file)
    const [path, body] = apiClient.post.mock.calls[0]
    expect(path).toBe('/settings/payment/apple-pay/association-file')
    expect(body).toBeInstanceOf(FormData)
    expect(body.get('file')).toBe(file)
  })

  it('logs only payload shapes, never a secret value', async () => {
    await paymentIntegrationService.update({ mode: 'test', test: { secretKey: SECRET } })
    await aiIntegrationService.update({ provider: 'openai', apiKey: SECRET })
    const breadcrumbs = JSON.stringify(logger.breadcrumb.mock.calls)
    expect(breadcrumbs).not.toContain(SECRET)
    expect(breadcrumbs).toContain('paymentIntegration.update')
  })

  it('treats provider errors and rate limits as expected answers but reports unexpected failures', async () => {
    apiClient.post.mockRejectedValueOnce({ response: { status: 502 } })
    await expect(paymentIntegrationService.testConnection('test')).rejects.toBeTruthy()
    apiClient.post.mockRejectedValueOnce({ response: { status: 429 } })
    await expect(smsIntegrationService.sendTest({ recipient: '+966500000001' })).rejects.toBeTruthy()
    expect(logger.error).not.toHaveBeenCalled()
    apiClient.get.mockRejectedValueOnce({ response: { status: 500 } })
    await expect(emailIntegrationService.get()).rejects.toBeTruthy()
    expect(logger.error).toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ action: 'emailIntegration.get' }))
  })
})

describe('entity follow-up services', () => {
  it('reads both profile change histories and the privacy entity picker', async () => {
    await profileChangesService.listMine({ page: 2, perPage: 10 })
    await profileChangesService.listForEntity(5001, { page: 1, perPage: 10 })
    await privacyRequestsService.lookupEntities({ search: 'ENT-0050', limit: 10 })
    expect(apiClient.get).toHaveBeenCalledWith('/entities/me/profile-changes', { params: { page: 2, perPage: 10 } })
    expect(apiClient.get).toHaveBeenCalledWith('/entities/5001/profile-changes', { params: { page: 1, perPage: 10 } })
    expect(apiClient.get).toHaveBeenCalledWith('/privacy/entities', { params: { search: 'ENT-0050', limit: 10 } })
  })
})
