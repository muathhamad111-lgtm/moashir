import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authSettingsService } from '@/services/authSettingsService'
import { phoneVerificationService } from '@/services/phoneVerificationService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

function respond(data) {
  return { data: { data, meta: {}, errors: null } }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('authSettingsService', () => {
  it('reads and changes the entity sign-in code channels through /settings/auth', async () => {
    apiClient.get.mockResolvedValue(
      respond({ entitySignInCodeChannels: ['email'], smsStatus: 'test', staffTotpRequired: true }),
    )
    apiClient.put.mockResolvedValue(
      respond({
        entitySignInCodeChannels: ['sms', 'email'],
        smsStatus: 'live',
        staffTotpRequired: true,
      }),
    )
    expect((await authSettingsService.get()).data).toEqual({
      entitySignInCodeChannels: ['email'],
      smsStatus: 'test',
      staffTotpRequired: true,
    })
    const payload = {
      entitySignInCodeChannels: ['email', 'sms'],
      reason: 'Approved by the client board.',
    }
    const saved = await authSettingsService.update(payload)
    expect(apiClient.get).toHaveBeenCalledWith('/settings/auth')
    expect(apiClient.put).toHaveBeenCalledWith('/settings/auth', payload)
    expect(saved.data.entitySignInCodeChannels).toEqual(['email', 'sms'])
    expect(saved.data.smsStatus).toBe('live')
    expect(JSON.stringify(logger.breadcrumb.mock.calls)).not.toContain('Approved by the client')
  })

  it('keeps the e-mail channel and reads an unknown SMS status as unavailable', async () => {
    apiClient.get.mockResolvedValue(respond({ entitySignInCodeChannels: ['sms', 'pigeon'] }))
    expect((await authSettingsService.get()).data).toEqual({
      entitySignInCodeChannels: ['email', 'sms'],
      smsStatus: 'unavailable',
      staffTotpRequired: false,
    })
  })

  it('treats 403 and 422 as expected answers', async () => {
    apiClient.get.mockRejectedValue({ response: { status: 403 } })
    apiClient.put.mockRejectedValue({ response: { status: 422 } })
    await expect(authSettingsService.get()).rejects.toBeTruthy()
    await expect(authSettingsService.update({})).rejects.toBeTruthy()
    expect(logger.error).not.toHaveBeenCalled()
  })
})

describe('phoneVerificationService', () => {
  it('starts and confirms the SMS verification of the signed-in account', async () => {
    apiClient.post.mockResolvedValue(respond({ challengeId: 'p-1', channel: 'sms', length: 6 }))
    await phoneVerificationService.start()
    await phoneVerificationService.confirm({ challengeId: 'p-1', code: '482913' })
    expect(apiClient.post).toHaveBeenNthCalledWith(1, '/me/phone/verification', {})
    expect(apiClient.post).toHaveBeenNthCalledWith(2, '/me/phone/verification/confirm', {
      challengeId: 'p-1',
      code: '482913',
    })
    expect(JSON.stringify(logger.breadcrumb.mock.calls)).not.toContain('482913')
  })

  it('does not report the missing SMS provider or a used number as errors', async () => {
    for (const status of [409, 422, 429, 503]) {
      apiClient.post.mockRejectedValueOnce({ response: { status } })
      await expect(phoneVerificationService.start()).rejects.toBeTruthy()
    }
    expect(logger.error).not.toHaveBeenCalled()
  })
})
