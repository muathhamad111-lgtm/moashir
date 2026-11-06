import { describe, expect, it, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useBrandingStore } from '@/stores/branding'
import { brandingService } from '@/services/brandingService'

vi.mock('@/services/brandingService', () => ({
  brandingService: { get: vi.fn() },
}))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('branding store', () => {
  it('starts on the brand defaults', () => {
    const store = useBrandingStore()
    expect(store.accentToken).toBe('brand_default')
    expect(store.logos).toEqual({ dark: null })
  })

  it('loads the shared theme once', async () => {
    brandingService.get.mockResolvedValue({
      data: {
        accentToken: 'coral',
        logos: { dark: 'blob:on-dark', light: 'blob:on-light' },
        palette: [],
      },
    })
    const store = useBrandingStore()
    await store.ensureLoaded()
    await store.ensureLoaded()
    expect(brandingService.get).toHaveBeenCalledTimes(1)
    expect(store.accentToken).toBe('coral')
    expect(store.logos).toEqual({ dark: 'blob:on-dark' })
  })

  it('ignores unknown accent tokens and falls back to the brand default', () => {
    const store = useBrandingStore()
    store.apply({ accentToken: '#ff0000' })
    expect(store.accentToken).toBe('brand_default')
  })

  it('keeps the defaults when the theme cannot be loaded', async () => {
    brandingService.get.mockRejectedValue(new Error('offline'))
    const store = useBrandingStore()
    await store.load()
    expect(store.status).toBe('error')
    expect(store.accentToken).toBe('brand_default')
  })
})
