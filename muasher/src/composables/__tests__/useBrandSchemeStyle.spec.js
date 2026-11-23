import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { brandSchemeCss, useBrandSchemeStyle } from '@/composables/useBrandSchemeStyle'
import { useBrandingStore } from '@/stores/branding'
import { deriveBrandScheme } from '@/utils/brandingContrast'

beforeEach(() => setActivePinia(createPinia()))

describe('useBrandSchemeStyle', () => {
  it('writes the brand and sidebar-rail custom properties for the back-office shell', () => {
    const css = brandSchemeCss(deriveBrandScheme({ primary: '#d4088c', secondary: '#4a2a7d' }))
    expect(css).toBe(
      "html:root[data-shell='tts']{--brand-primary:#d4088c;--brand-on-primary:#ffffff;" +
        '--brand-primary-text:#c90885;--brand-primary-hover:#bc097d;--brand-primary-tint:#fbe6f4;' +
        '--brand-secondary:#4a2a7d;--brand-on-secondary:#ffffff;' +
        '--brand-rail-top:#4a2a7d;--brand-rail-bottom:#6d2d82;--brand-on-rail:#ffffff;' +
        '--brand-on-rail-muted:#d5c8de;--brand-rail-accent:#f9b8d6;' +
        '--brand-rail-gradient:linear-gradient(180deg, #4a2a7d 0%, #4e2b7e 12.5%, #532b7e 25%, ' +
        '#572c7f 37.5%, #5c2c80 50%, #602c80 62.5%, #642d81 75%, #692d81 87.5%, #6d2d82 100%);' +
        '--brand-rail-glow:40%}',
    )
  })

  it('writes nothing for a scheme whose rail stops are not hex colours', () => {
    const scheme = deriveBrandScheme({ primary: '#d4088c', secondary: '#4a2a7d' })
    expect(brandSchemeCss({ ...scheme, railStops: ['#4a2a7d', 'red);}body{x:y'] })).toBe('')
    expect(brandSchemeCss({ ...scheme, railGlow: '40%;}' })).toBe('')
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
