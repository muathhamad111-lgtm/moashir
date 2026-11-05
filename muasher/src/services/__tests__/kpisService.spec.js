import { describe, expect, it, vi, beforeEach } from 'vitest'
import { kpisService } from '@/services/kpisService'
import { kpisAdminService } from '@/services/kpisAdminService'
import { kpiCatalogService } from '@/services/kpiCatalogService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({ logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() } }))

const envelope = { data: { data: [], meta: {}, errors: null } }

beforeEach(() => {
  vi.clearAllMocks()
  Object.values(apiClient).forEach((method) => method.mockResolvedValue(envelope))
})

describe('kpisService (public)', () => {
  it('lists published indicators with server-side filters, sort and paging', async () => {
    const params = { search: 'الجمعيات', 'filter[categoryId]': 2, 'filter[status]': 'needs-improvement', sort: '-value', page: 2, perPage: 12 }
    await kpisService.list(params)
    expect(apiClient.get).toHaveBeenCalledWith('/kpis', { params })
    expect(logger.breadcrumb).toHaveBeenCalledWith('service', 'kpis.list', expect.objectContaining({ entity: 'kpi' }))
  })

  it('reads the featured list, a detail by slug and the public catalogs', async () => {
    await kpisService.getFeatured()
    await kpisService.getBySlug('volunteers')
    await kpisService.listCategories()
    await kpisService.listTypes()
    expect(apiClient.get.mock.calls.map(([url]) => url)).toEqual(['/kpis/featured', '/kpis/volunteers', '/kpi-categories', '/kpi-types'])
  })

  it('does not report a 404 detail as an error', async () => {
    const missing = Object.assign(new Error('missing'), { response: { status: 404 } })
    apiClient.get.mockRejectedValue(missing)
    await expect(kpisService.getBySlug('unknown')).rejects.toBe(missing)
    expect(logger.error).not.toHaveBeenCalled()
  })
})

describe('kpisAdminService', () => {
  it('maps every editor action to its contract path and verb', async () => {
    await kpisAdminService.list({ page: 1 })
    await kpisAdminService.get(7)
    await kpisAdminService.create({ name: { ar: 'مؤشر', en: '' } })
    await kpisAdminService.update(7, { showOnHomepage: true })
    await kpisAdminService.archive(7)
    await kpisAdminService.setPublished(7, true)
    await kpisAdminService.move(7, 'up')
    await kpisAdminService.reorder([2, 7])
    await kpisAdminService.listValues(7)
    await kpisAdminService.recordValue(7, { value: '12.5', measuredOn: '2026-10-01' })
    expect(apiClient.get.mock.calls.map(([url]) => url)).toEqual(['/admin/kpis', '/admin/kpis/7', '/admin/kpis/7/values'])
    expect(apiClient.post.mock.calls).toEqual([
      ['/admin/kpis', { name: { ar: 'مؤشر', en: '' } }],
      ['/admin/kpis/7/values', { value: '12.5', measuredOn: '2026-10-01' }],
    ])
    expect(apiClient.put).toHaveBeenCalledWith('/admin/kpis/7', { showOnHomepage: true })
    expect(apiClient.delete).toHaveBeenCalledWith('/admin/kpis/7')
    expect(apiClient.patch.mock.calls).toEqual([
      ['/admin/kpis/7/published', { published: true }],
      ['/admin/kpis/7/reorder', { direction: 'up' }],
      ['/admin/kpis/reorder', { orderedIds: [2, 7] }],
    ])
  })

  it('uploads the KPI image as multipart', async () => {
    await kpisAdminService.uploadImage(new File(['jpg'], 'kpi.jpg', { type: 'image/jpeg' }))
    expect(apiClient.post.mock.calls[0][0]).toBe('/admin/kpis/images')
  })
})

describe('kpiCatalogService', () => {
  it('manages categories and types on their admin paths', async () => {
    await kpiCatalogService.categories.list()
    await kpiCatalogService.categories.create({ key: 'impact' })
    await kpiCatalogService.categories.update(3, { active: false })
    await kpiCatalogService.types.remove(2)
    expect(apiClient.get).toHaveBeenCalledWith('/admin/kpi-categories')
    expect(apiClient.post).toHaveBeenCalledWith('/admin/kpi-categories', { key: 'impact' })
    expect(apiClient.put).toHaveBeenCalledWith('/admin/kpi-categories/3', { active: false })
    expect(apiClient.delete).toHaveBeenCalledWith('/admin/kpi-types/2')
  })
})
