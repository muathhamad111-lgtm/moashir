import { describe, expect, it } from 'vitest'
import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'
import { useSettings, EMAIL_SEGMENTS } from '@/composables/useSettings'
import { createTestGlobals } from '@/test-utils'

function withComposable(locale = 'en') {
  let api
  const Harness = defineComponent({
    setup() {
      api = useSettings()
      return () => null
    },
  })
  mount(Harness, { global: createTestGlobals({ locale }) })
  return api
}

describe('useSettings', () => {
  it('resolves bilingual { ar, en } content to the active locale with fallback', () => {
    const en = withComposable('en')
    expect(en.localized({ ar: 'مرحبا', en: 'Hello' })).toBe('Hello')
    expect(en.localized('plain')).toBe('plain')
    expect(en.localized(null)).toBe('')
    expect(en.localized({ ar: 'عربي' })).toBe('عربي')

    const ar = withComposable('ar')
    expect(ar.localized({ ar: 'مرحبا', en: 'Hello' })).toBe('مرحبا')
  })

  it('exposes the §4 email-segment enum in the documented order', () => {
    expect(EMAIL_SEGMENTS).toEqual([
      'self-only-not-accredited',
      'registered-advanced-not-qualified',
      'qualified',
      'last-was-accredited',
      'all',
      'individual',
    ])
  })

  it('labels certificate kinds and segments through i18n', () => {
    const { certificateKindLabel, segmentLabel } = withComposable('en')
    expect(certificateKindLabel('advanced-winner')).toBe('Advanced — winner')
    expect(segmentLabel('qualified')).toBe('Qualified entities')
  })
})
