import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { brandSchemeCss, useBrandSchemeStyle } from '@/composables/useBrandSchemeStyle'
import { useBrandingStore } from '@/stores/branding'
import { deriveBrandScheme } from '@/utils/brandingContrast'

beforeEach(() => setActivePinia(createPinia()))

describe('useBrandSchemeStyle', () => {
  it('writes the five brand custom properties for the back-office shell', () => {
    const css = brandSchemeCss(deriveBrandScheme({ primary: '#d4088c', secondary: '#4a2a7d' }))
    expect(css).toBe(
      "html:root[data-shell='tts']{--brand-primary:#d4088c;--brand-on-primary:#ffffff;" +
        '--brand-primary-text:#d4088c;--brand-secondary:#4a2a7d;--brand-on-secondary:#ffffff}',
    )
  })

  it('writes nothing for an unsafe or incomplete scheme', () => {
    expect(brandSchemeCss({ primary: 'red;}body{display:none' })).toBe('')
    expect(brandSchemeCss(null)).toBe('')
  })

  it('follows the saved scheme in the branding store', () => {
    const style = useBrandSchemeStyle()
    expect(style.value).toContain('--brand-primary:#98d8d3')
    useBrandingStore().apply({
      scheme: deriveBrandScheme({ primary: '#0b7068', secondary: '#0a2325' }),
    })
    expect(style.value).toContain('--brand-primary:#0b7068')
  })
})
