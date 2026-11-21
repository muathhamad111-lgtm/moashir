import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePublicHomeStore } from '@/stores/publicHome'
import { useFeaturedKpisStore } from '@/stores/featuredKpis'
import { useSiteStatusStore } from '@/stores/siteStatus'
import { contentService } from '@/services/contentService'
import { kpisService } from '@/services/kpisService'

vi.mock('@/services/contentService', () => ({ contentService: { getHome: vi.fn() } }))
vi.mock('@/services/kpisService', () => ({ kpisService: { getFeatured: vi.fn() } }))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('public home store', () => {
  it('loads the published home once and shares it with every consumer', async () => {
    contentService.getHome.mockResolvedValue({
      data: {
        published: true,
        maintenance: false,
        sections: [{ id: 1, type: 'hero' }],
        siteSettings: { featuredKpiLimit: 6 },
        seo: null,
      },
    })
    const store = usePublicHomeStore()
    await store.ensureLoaded()
    await store.ensureLoaded()
    expect(contentService.getHome).toHaveBeenCalledTimes(1)
    expect(store.status).toBe('success')
    expect(store.siteSettings.featuredKpiLimit).toBe(6)
    expect(useSiteStatusStore().maintenance).toBe(false)
  })

  it('switches the site to maintenance when the home answers the maintenance payload', async () => {
    contentService.getHome.mockResolvedValue({ data: { published: false, maintenance: true } })
    const store = usePublicHomeStore()
    await store.load()
    expect(store.status).toBe('empty')
    expect(useSiteStatusStore().maintenance).toBe(true)
  })
})

describe('featured KPI store', () => {
  it('shows the featured list only from the minimum the server reports', async () => {
    kpisService.getFeatured.mockResolvedValue({
      data: [{ id: 1 }, { id: 2 }],
      meta: { minimum: 3, count: 2 },
    })
    const store = useFeaturedKpisStore()
    await store.ensureLoaded()
    expect(store.kpis).toHaveLength(2)
    expect(store.shown).toEqual([])
    kpisService.getFeatured.mockResolvedValue({
      data: [{ id: 1 }, { id: 2 }, { id: 3 }],
      meta: { minimum: 3, count: 3 },
    })
    await store.load()
    expect(store.shown).toHaveLength(3)
  })
})
