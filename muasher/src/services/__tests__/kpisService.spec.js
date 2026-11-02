import { describe, expect, it, vi, beforeEach } from 'vitest'
import { kpisService } from '@/services/kpisService'
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

describe('kpisService', () => {
  it('lists the PUBLIC indicators and unwraps the envelope', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    const result = await kpisService.list()
    expect(apiClient.get).toHaveBeenCalledWith('/kpis', { params: {} })
    expect(result).toEqual(envelope)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'kpis.list',
      expect.objectContaining({ entity: 'kpi' }),
    )
  })

  it('lists ALL indicators for the admin CMS with a params-shape breadcrumb', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    const params = { search: 'الجمعيات', 'filter[published]': 'true', page: 2 }
    const result = await kpisService.adminList(params)
    expect(apiClient.get).toHaveBeenCalledWith('/admin/kpis', { params })
    expect(result).toEqual(envelope)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'kpis.adminList',
      expect.objectContaining({
        entity: 'kpi',
        paramsShape: ['search', 'filter[published]', 'page'],
      }),
    )
  })

  it('gets the featured (homepage) indicators from the public endpoint', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await kpisService.getFeatured()
    expect(apiClient.get).toHaveBeenCalledWith('/kpis', { params: { homepage: true } })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'kpis.getFeatured',
      expect.objectContaining({ entity: 'kpi' }),
    )
  })

  it('gets a single indicator from the admin endpoint', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await kpisService.get(12)
    expect(apiClient.get).toHaveBeenCalledWith('/admin/kpis/12')
  })

  it('gets a single PUBLIC indicator by slug for the detail view', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await kpisService.getBySlug('participating-associations')
    expect(apiClient.get).toHaveBeenCalledWith('/kpis/participating-associations')
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'kpis.getBySlug',
      expect.objectContaining({ entity: 'kpi' }),
    )
  })

  it('creates an indicator via POST and logs only the payload shape', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    const payload = {
      name: { ar: 'المتطوعون', en: 'Volunteers' },
      value: 214000,
      target: 300000,
      unit: { ar: 'متطوع', en: 'volunteers' },
      icon: 'fa-solid fa-hands-holding-circle',
      color: 'primary',
      published: true,
    }
    await kpisService.create(payload)
    expect(apiClient.post).toHaveBeenCalledWith('/admin/kpis', payload)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'kpis.create',
      expect.objectContaining({
        payloadShape: ['name', 'value', 'target', 'unit', 'icon', 'color', 'published'],
      }),
    )
  })

  it('updates an indicator via PUT', async () => {
    apiClient.put.mockResolvedValue({ data: envelope })
    await kpisService.update(3, { value: 75 })
    expect(apiClient.put).toHaveBeenCalledWith('/admin/kpis/3', { value: 75 })
  })

  it('removes an indicator via DELETE', async () => {
    apiClient.delete.mockResolvedValue({ data: envelope })
    await kpisService.remove(9)
    expect(apiClient.delete).toHaveBeenCalledWith('/admin/kpis/9')
  })

  it('sets the published state via PATCH with a boolean body', async () => {
    apiClient.patch.mockResolvedValue({ data: envelope })
    await kpisService.setPublished(5, false)
    expect(apiClient.patch).toHaveBeenCalledWith('/admin/kpis/5/published', { published: false })
  })

  it('reorders an indicator via PATCH with a direction body', async () => {
    apiClient.patch.mockResolvedValue({ data: envelope })
    await kpisService.reorder(7, 'up')
    expect(apiClient.patch).toHaveBeenCalledWith('/admin/kpis/7/reorder', { direction: 'up' })
  })

  it('reports failures to Sentry and rethrows (never swallows)', async () => {
    const failure = new Error('boom')
    apiClient.get.mockRejectedValue(failure)
    await expect(kpisService.adminList()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'kpis.adminList' }),
    )
  })
})
