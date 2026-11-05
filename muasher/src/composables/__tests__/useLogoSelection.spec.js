import { describe, expect, it } from 'vitest'
import { logoErrorKey } from '@/composables/useLogoSelection'

describe('logoErrorKey', () => {
  it('accepts PNG and SVG up to 1 MB', () => {
    expect(logoErrorKey(new File(['x'], 'a.png', { type: 'image/png' }))).toBe('')
    expect(logoErrorKey(new File(['x'], 'a.svg', { type: 'image/svg+xml' }))).toBe('')
  })

  it('rejects other types and oversized files', () => {
    expect(logoErrorKey(new File(['x'], 'a.jpg', { type: 'image/jpeg' }))).toBe(
      'branding.logo.invalidType',
    )
    const big = new File([new Uint8Array(1048577)], 'a.png', { type: 'image/png' })
    expect(logoErrorKey(big)).toBe('branding.logo.tooLarge')
  })
})
