import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { brandSchemeCss, useBrandSchemeStyle } from '@/composables/useBrandSchemeStyle'
import { useBrandingStore } from '@/stores/branding'
import { deriveBrandScheme } from '@/utils/brandingContrast'

beforeEach(() => setActivePinia(createPinia()))

describe('useBrandSchemeStyle', () => {
  it('writes the seven brand custom properties for the back-office shell', () => {
    const css = brandSchemeCss(deriveBrandScheme({ primary: '#d4088c', secondary: '#4a2a7d' }))
    expect(css).toBe(
      "html:root[data-shell='tts']{--brand-primary:#d4088c;--brand-on-primary:#ffffff;" +
        '--brand-primary-text:#c90885;--brand-primary-hover:#bc097d;--brand-primary-tint:#fbe6f4;' +
        '--brand-secondary:#4a2a7d;--brand-on-secondary:#ffffff}',
    )
  })

  it('writes an unsaved draft under its own prefix on the given container', () => {
    const css = brandSchemeCss(deriveBrandScheme({ primary: '#98d8d3', secondary: '#0a2325' }), {
      selector: '.branding-editor',
      prefix: 'draft',
    })
    expect(css.startsWith('.branding-editor{--draft-primary:#98d8d3;')).toBe(true)
    expect(css).toContain('--draft-primary-hover:#88c0bc')
    expect(css).not.toContain('--brand-')
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
