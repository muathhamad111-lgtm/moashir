import { describe, expect, it, vi, beforeEach } from 'vitest'
import { siteSettingsService } from '@/services/siteSettingsService'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn(), patch: vi.fn() } }))
vi.mock('@/utils/logger', () => ({ logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() } }))

beforeEach(() => vi.clearAllMocks())

describe('siteSettingsService', () => {
  it('reads and patches the site settings', async () => {
    apiClient.get.mockResolvedValue({ data: { data: { featuredKpiLimit: 6 } } })
    apiClient.patch.mockResolvedValue({ data: { data: { featuredKpiLimit: 4 } } })
    expect((await siteSettingsService.get()).data.featuredKpiLimit).toBe(6)
    await siteSettingsService.update({ featuredKpiLimit: 4 })
    expect(apiClient.get).toHaveBeenCalledWith('/admin/content/site-settings')
    expect(apiClient.patch).toHaveBeenCalledWith('/admin/content/site-settings', { featuredKpiLimit: 4 })
  })
})
