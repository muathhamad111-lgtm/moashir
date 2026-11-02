import { describe, expect, it, vi, beforeEach } from 'vitest'
import { contentService } from '@/services/contentService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

beforeEach(() => {
  vi.clearAllMocks()
})

function ok(data) {
  return { data: { data, meta: {}, errors: null } }
}

describe('contentService', () => {
  it('loads the landing content through the axios client', async () => {
    const envelope = { data: { published: true }, meta: {}, errors: null }
    apiClient.get.mockResolvedValue({ data: envelope })
    const result = await contentService.getLanding()
    expect(apiClient.get).toHaveBeenCalledWith('/content/landing')
    expect(result).toEqual(envelope)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'content.getLanding',
      expect.objectContaining({ entity: 'landing-content' }),
    )
  })

  it('loads the model-criteria snapshot', async () => {
    apiClient.get.mockResolvedValue({ data: { data: [], meta: {}, errors: null } })
    await contentService.getModelCriteria()
    expect(apiClient.get).toHaveBeenCalledWith('/content/model-criteria')
  })

  it('reports failures to Sentry and rethrows', async () => {
    const failure = new Error('down')
    apiClient.get.mockRejectedValue(failure)
    await expect(contentService.getLanding()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'content.getLanding' }),
    )
  })

  it('loads the public home over /content/home', async () => {
    apiClient.get.mockResolvedValue(ok({ sections: [], siteSettings: {} }))
    await contentService.getHome()
    expect(apiClient.get).toHaveBeenCalledWith('/content/home')
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'content.getHome',
      expect.objectContaining({ entity: 'home-content' }),
    )
  })

  it('loads the admin home over /admin/content/home', async () => {
    apiClient.get.mockResolvedValue(ok({ sections: [], siteSettings: {} }))
    await contentService.getHomeAdmin()
    expect(apiClient.get).toHaveBeenCalledWith('/admin/content/home')
  })

  it('updates a section', async () => {
    apiClient.patch.mockResolvedValue(ok({ id: 'sec-hero' }))
    await contentService.updateSection('sec-hero', { title: { ar: 'ا', en: 'A' } })
    expect(apiClient.patch).toHaveBeenCalledWith('/admin/content/sections/sec-hero', {
      title: { ar: 'ا', en: 'A' },
    })
  })

  it('toggles a section', async () => {
    apiClient.patch.mockResolvedValue(ok({ id: 'sec-hero', enabled: false }))
    await contentService.toggleSection('sec-hero', false)
    expect(apiClient.patch).toHaveBeenCalledWith('/admin/content/sections/sec-hero/enabled', {
      enabled: false,
    })
  })

  it('reorders sections', async () => {
    apiClient.patch.mockResolvedValue(ok([]))
    await contentService.reorderSections(['a', 'b'])
    expect(apiClient.patch).toHaveBeenCalledWith('/admin/content/sections/reorder', {
      orderedIds: ['a', 'b'],
    })
  })

  it('upserts an item', async () => {
    apiClient.post.mockResolvedValue(ok({ id: 'sec-partners' }))
    await contentService.upsertItem('sec-partners', { title: { ar: 'ش', en: 'P' } })
    expect(apiClient.post).toHaveBeenCalledWith('/admin/content/sections/sec-partners/items', {
      title: { ar: 'ش', en: 'P' },
    })
  })

  it('removes an item', async () => {
    apiClient.delete.mockResolvedValue(ok({}))
    await contentService.removeItem('sec-partners', 'partner-1')
    expect(apiClient.delete).toHaveBeenCalledWith(
      '/admin/content/sections/sec-partners/items/partner-1',
    )
  })

  it('reorders items', async () => {
    apiClient.patch.mockResolvedValue(ok({}))
    await contentService.reorderItems('sec-partners', ['partner-2', 'partner-1'])
    expect(apiClient.patch).toHaveBeenCalledWith(
      '/admin/content/sections/sec-partners/items/reorder',
      { orderedIds: ['partner-2', 'partner-1'] },
    )
  })

  it('reads and updates site settings', async () => {
    apiClient.get.mockResolvedValue(ok({ social: [] }))
    apiClient.patch.mockResolvedValue(ok({ social: [{ platform: 'x', url: 'u' }] }))
    await contentService.getSiteSettings()
    expect(apiClient.get).toHaveBeenCalledWith('/admin/content/site-settings')
    await contentService.updateSiteSettings({ footerNote: { ar: 'ا', en: 'A' } })
    expect(apiClient.patch).toHaveBeenCalledWith('/admin/content/site-settings', {
      footerNote: { ar: 'ا', en: 'A' },
    })
  })
})
