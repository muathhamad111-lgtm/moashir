import { describe, expect, it } from 'vitest'
import { DEFAULT_BRAND_COLORS } from '@/constants/branding'
import { deriveBrandScheme } from '@/utils/brandingContrast'
import { brandWarnings } from '@/utils/brandingWarnings'

describe('brand colour warnings', () => {
  it('has nothing to say about the default colours', () => {
    expect(brandWarnings(deriveBrandScheme(DEFAULT_BRAND_COLORS))).toEqual([])
    expect(brandWarnings(null)).toEqual([])
  })

  it('warns when the primary colour reads as the error or the warning colour', () => {
    expect(brandWarnings(deriveBrandScheme({ primary: '#d63031', secondary: '#0a2325' }))).toEqual([
      { key: 'nearAlert', status: 'danger' },
    ])
    expect(brandWarnings(deriveBrandScheme({ primary: '#ffa31a', secondary: '#0a2325' }))).toEqual([
      { key: 'nearAlert', status: 'warning' },
    ])
  })

  it('warns when the hover colour cannot be told from the fill', () => {
    const scheme = deriveBrandScheme(DEFAULT_BRAND_COLORS, { primaryHover: '#97d7d2' })
    expect(brandWarnings(scheme)).toEqual([{ key: 'hoverIndistinct' }])
  })
})
