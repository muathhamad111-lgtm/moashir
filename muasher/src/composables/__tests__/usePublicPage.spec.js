import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { usePublicPage } from '@/composables/usePublicPage'
import { useKpiFilters } from '@/composables/useKpiFilters'
import { contentService } from '@/services/contentService'
import { PUBLIC_PAGE_STATES } from '@/constants/cms'

vi.mock('@/services/contentService', () => ({ contentService: { getPage: vi.fn() } }))

beforeEach(() => vi.clearAllMocks())

describe('usePublicPage', () => {
  it('exposes the sections by type once the page is published', async () => {
    contentService.getPage.mockResolvedValue({
      data: {
        key: 'advanced_intro',
        sections: [
          { id: 1, type: 'rich_text' },
          { id: 2, type: 'cycle_box' },
          { id: 3, type: 'rich_text' },
        ],
      },
    })
    const { api: page } = withSetup(() => usePublicPage('advanced_intro'))
    await flushPromises()
    expect(page.state.value).toBe(PUBLIC_PAGE_STATES.READY)
    expect(page.sectionOfType('rich_text', 1).id).toBe(3)
    expect(page.sectionsOfType('cycle_box')).toHaveLength(1)
    expect(page.sectionOfType('faq')).toBeNull()
  })

  it('separates an unpublished page (404) from a failure', async () => {
    contentService.getPage.mockRejectedValueOnce(
      Object.assign(new Error('hidden'), { response: { status: 404 } }),
    )
    const { api: hidden } = withSetup(() => usePublicPage('lenses'))
    await flushPromises()
    expect(hidden.state.value).toBe(PUBLIC_PAGE_STATES.UNPUBLISHED)
    contentService.getPage.mockRejectedValueOnce(
      Object.assign(new Error('down'), { response: { status: 503 } }),
    )
    const { api: failed } = withSetup(() => usePublicPage('lenses'))
    await flushPromises()
    expect(failed.state.value).toBe(PUBLIC_PAGE_STATES.ERROR)
  })
})

describe('useKpiFilters', () => {
  it('builds server parameters only for active filters and searchable terms', () => {
    const filters = useKpiFilters()
    expect(filters.params.value).toEqual({})
    filters.set('category', 2)
    filters.set('status', 'behind-target')
    filters.set('owner', 'a')
    filters.set('search', ' مؤشر ')
    filters.set('sort', '-value')
    expect(filters.params.value).toEqual({
      search: 'مؤشر',
      'filter[categoryId]': 2,
      'filter[status]': 'behind-target',
      sort: '-value',
    })
    expect(filters.isFiltering.value).toBe(true)
    filters.reset()
    expect(filters.isFiltering.value).toBe(false)
  })
})
