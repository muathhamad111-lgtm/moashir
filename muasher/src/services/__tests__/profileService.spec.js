import { describe, expect, it, vi, beforeEach } from 'vitest'
import { profileService } from '@/services/profileService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn(), patch: vi.fn() } }))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

beforeEach(() => vi.clearAllMocks())

describe('profileService.updatePreferences', () => {
  it('saves the language to the profile with PATCH /me/preferences', async () => {
    apiClient.patch.mockResolvedValue({
      data: { data: { locale: 'en', theme: 'system' }, meta: {}, errors: null },
    })
    const envelope = await profileService.updatePreferences({ locale: 'en' })
    expect(apiClient.patch).toHaveBeenCalledWith('/me/preferences', { locale: 'en' })
    expect(envelope.data.locale).toBe('en')
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'profile.updatePreferences',
      expect.objectContaining({ payloadShape: ['locale'] }),
    )
  })
})
