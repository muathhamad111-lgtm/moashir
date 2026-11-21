import { describe, expect, it, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useBrandingStore } from '@/stores/branding'
import { brandingService } from '@/services/brandingService'
import { deriveBrandScheme } from '@/utils/brandingContrast'

vi.mock('@/services/brandingService', () => ({
  brandingService: { get: vi.fn() },
}))

const TTS = deriveBrandScheme({ primary: '#d4088c', secondary: '#4a2a7d' })

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('branding store', () => {
  it('starts on the Moashir default scheme', () => {
    const store = useBrandingStore()
    expect(store.scheme).toEqual(deriveBrandScheme({ primary: '#98d8d3', secondary: '#0a2325' }))
    expect(store.logos).toEqual({ dark: null, favicon: null, site: null })
  })

  it('loads the shared theme once', async () => {
    brandingService.get.mockResolvedValue({
      data: { scheme: TTS, logos: { dark: 'blob:on-dark', light: 'blob:on-light' } },
    })
    const store = useBrandingStore()
    await store.ensureLoaded()
    await store.ensureLoaded()
    expect(brandingService.get).toHaveBeenCalledTimes(1)
    expect(store.scheme).toEqual(TTS)
    expect(store.logos).toEqual({ dark: 'blob:on-dark', favicon: null, site: null })
  })

  it('ignores an incomplete or unsafe scheme and keeps the default', () => {
    const store = useBrandingStore()
    store.apply({ scheme: { ...TTS, onSecondary: 'white;}body{display:none' } })
    expect(store.scheme.primary).toBe('#98d8d3')
  })

  it('keeps the defaults when the theme cannot be loaded', async () => {
    brandingService.get.mockRejectedValue(new Error('offline'))
    const store = useBrandingStore()
    await store.load()
    expect(store.status).toBe('error')
    expect(store.scheme.primary).toBe('#98d8d3')
  })
})
