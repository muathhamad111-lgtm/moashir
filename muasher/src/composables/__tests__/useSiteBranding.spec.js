import { describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSiteBranding } from '@/composables/useSiteBranding'
import { usePublicHomeStore } from '@/stores/publicHome'
import { useBrandingStore } from '@/stores/branding'

describe('useSiteBranding', () => {
  it('has no custom logo or favicon by default', () => {
    setActivePinia(createPinia())
    const { siteLogo, favicon } = useSiteBranding()
    expect(siteLogo.value).toBe('')
    expect(favicon.value).toBe('')
  })

  it('reads the public site logo and favicon from the public settings', () => {
    setActivePinia(createPinia())
    usePublicHomeStore().siteSettings = {
      branding: {
        siteLogo: { fileId: 'a', url: '/api/v1/content/files/a' },
        favicon: { fileId: 'b', url: '/api/v1/content/files/b' },
      },
    }
    const { siteLogo, favicon } = useSiteBranding()
    expect(siteLogo.value).toMatch(/files\/a$/)
    expect(favicon.value).toMatch(/files\/b$/)
  })

  it('prefers the favicon of the signed-in theme', () => {
    setActivePinia(createPinia())
    useBrandingStore().apply({ accentToken: 'brand_default', logos: { favicon: 'blob:icon' } })
    const { favicon } = useSiteBranding()
    expect(favicon.value).toBe('blob:icon')
  })
})
