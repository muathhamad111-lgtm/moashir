import { beforeEach, describe, expect, it, vi } from 'vitest'
import { legalService } from '@/services/legalService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'
import { toLegalDocument, toLegalPage } from '@/services/mappers/legal'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

beforeEach(() => vi.clearAllMocks())

describe('legalService', () => {
  it('reads the current published version of a legal text', async () => {
    apiClient.get.mockResolvedValue({ data: { data: { kind: 'privacy' } } })
    await legalService.getCurrent('privacy')
    expect(apiClient.get).toHaveBeenCalledWith('/content/legal/privacy')
  })

  it('treats a missing publication as expected', async () => {
    apiClient.get.mockRejectedValue(
      Object.assign(new Error('HTTP 404'), { response: { status: 404 } }),
    )
    await expect(legalService.getCurrent('terms')).rejects.toThrow('HTTP 404')
    expect(logger.error).not.toHaveBeenCalled()
  })
})

describe('legal mappers', () => {
  it('turns a versioned text into one section of blocks', () => {
    const document = toLegalDocument({
      kind: 'terms',
      version: '2026-11',
      publishedAt: '2026-11-04T11:20:05+00:00',
      title: { ar: 'شروط الاستخدام', en: 'Terms of use' },
      body: { ar: [{ type: 'paragraph', text: 'نص' }], en: [] },
    })
    expect(document).toMatchObject({ version: '2026-11', updatedAt: '2026-11-04T11:20:05+00:00' })
    expect(document.sections).toHaveLength(1)
  })

  it('keeps only the CMS sections that carry blocks', () => {
    const page = toLegalPage({
      title: { ar: 'إمكانية الوصول', en: 'Accessibility' },
      updatedAt: '2026-11-04T11:20:04+00:00',
      sections: [
        { id: 1, title: null, blocks: { ar: [], en: [] } },
        { id: 2, title: null, type: 'cards' },
      ],
    })
    expect(page.sections.map((section) => section.id)).toEqual([1])
    expect(page.updatedAt).toBe('2026-11-04T11:20:04+00:00')
  })
})
