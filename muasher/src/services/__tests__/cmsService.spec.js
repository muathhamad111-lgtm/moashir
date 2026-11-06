import { describe, expect, it, vi, beforeEach } from 'vitest'
import { cmsService } from '@/services/cmsService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: { data: {}, meta: {}, errors: null } }

beforeEach(() => {
  vi.clearAllMocks()
  Object.values(apiClient).forEach((method) => method.mockResolvedValue(envelope))
})

describe('cmsService (Content Officer endpoints)', () => {
  it('reads the pages, one page and the home editor payload', async () => {
    await cmsService.listPages()
    await cmsService.getPage('advanced_intro')
    await cmsService.getHome()
    expect(apiClient.get.mock.calls.map(([url]) => url)).toEqual([
      '/admin/content/pages',
      '/admin/content/pages/advanced_intro',
      '/admin/content/home',
    ])
  })

  it('updates page details with PUT and publishes or unpublishes with POST verbs', async () => {
    const details = {
      title: { ar: 'العنوان', en: 'Title' },
      seo: { title: null, description: null },
    }
    await cmsService.updatePage('home', details)
    await cmsService.publishPage('home')
    await cmsService.unpublishPage('home')
    expect(apiClient.put).toHaveBeenCalledWith('/admin/content/pages/home', details)
    expect(apiClient.post).toHaveBeenNthCalledWith(1, '/admin/content/pages/home/publish')
    expect(apiClient.post).toHaveBeenNthCalledWith(2, '/admin/content/pages/home/unpublish')
  })

  it('creates, patches, toggles, deletes and reorders sections on the contract paths', async () => {
    await cmsService.createSection('home', { key: 'faq', type: 'faq' })
    await cmsService.updateSection(12, { title: { ar: 'أ', en: 'A' } })
    await cmsService.toggleSection(12, false)
    await cmsService.removeSection(12)
    await cmsService.reorderSections([3, 1, 2])
    expect(apiClient.post).toHaveBeenCalledWith('/admin/content/pages/home/sections', {
      key: 'faq',
      type: 'faq',
    })
    expect(apiClient.patch.mock.calls).toEqual([
      ['/admin/content/sections/12', { title: { ar: 'أ', en: 'A' } }],
      ['/admin/content/sections/12/enabled', { enabled: false }],
      ['/admin/content/sections/reorder', { orderedIds: [3, 1, 2] }],
    ])
    expect(apiClient.delete).toHaveBeenCalledWith('/admin/content/sections/12')
  })

  it('creates items with POST, updates them with PUT and toggles, removes, reorders them', async () => {
    await cmsService.createItem(5, { title: { ar: 'خطوة', en: 'Step' } })
    await cmsService.updateItem(5, 40, { icon: 'fa-solid fa-star' })
    await cmsService.toggleItem(5, 40, true)
    await cmsService.removeItem(5, 40)
    await cmsService.reorderItems(5, [41, 40])
    expect(apiClient.post).toHaveBeenCalledWith('/admin/content/sections/5/items', {
      title: { ar: 'خطوة', en: 'Step' },
    })
    expect(apiClient.put).toHaveBeenCalledWith('/admin/content/sections/5/items/40', {
      icon: 'fa-solid fa-star',
    })
    expect(apiClient.patch.mock.calls).toEqual([
      ['/admin/content/sections/5/items/40/enabled', { enabled: true }],
      ['/admin/content/sections/5/items/reorder', { orderedIds: [41, 40] }],
    ])
    expect(apiClient.delete).toHaveBeenCalledWith('/admin/content/sections/5/items/40')
  })

  it('uploads an image as multipart and logs only the file type', async () => {
    const file = new File(['png'], 'hero.png', { type: 'image/png' })
    await cmsService.uploadImage(file)
    const [url, form] = apiClient.post.mock.calls[0]
    expect(url).toBe('/admin/content/images')
    expect(form.get('file')).toBeInstanceOf(File)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'cms.uploadImage',
      expect.objectContaining({ payloadShape: ['fileType'] }),
    )
  })

  it('treats contract rejections as expected answers and still rethrows them', async () => {
    const rejected = Object.assign(new Error('empty'), { response: { status: 422 } })
    apiClient.post.mockRejectedValue(rejected)
    await expect(cmsService.publishPage('kpis')).rejects.toBe(rejected)
    expect(logger.error).not.toHaveBeenCalled()
  })
})
