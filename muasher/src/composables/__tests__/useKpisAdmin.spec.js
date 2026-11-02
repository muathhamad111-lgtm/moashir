import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createTestGlobals } from '@/test-utils'
import {
  useKpisAdmin,
  KPI_COLOR_TOKENS,
  KPI_ICON_OPTIONS,
} from '@/composables/useKpisAdmin'

function withComposable(locale = 'en') {
  let api
  const Host = defineComponent({
    setup() {
      api = useKpisAdmin()
      return () => null
    },
  })
  mount(Host, { global: createTestGlobals({ locale }) })
  return api
}

describe('useKpisAdmin', () => {
  it('exposes exactly the five semantic color tokens (no free hex)', () => {
    expect(KPI_COLOR_TOKENS).toEqual(['primary', 'success', 'info', 'warning', 'danger'])
  })

  it('offers only FA Pro icon classes in the icon picker', () => {
    expect(KPI_ICON_OPTIONS.length).toBeGreaterThan(10)
    expect(KPI_ICON_OPTIONS.every((icon) => icon.startsWith('fa-'))).toBe(true)
  })

  it('builds localized color options from the token labels', () => {
    const { colorOptions } = withComposable('en')
    expect(colorOptions.value).toHaveLength(5)
    expect(colorOptions.value[0]).toEqual({ value: 'primary', label: 'Primary' })
    expect(colorOptions.value.map((o) => o.value)).toEqual(KPI_COLOR_TOKENS)
  })

  it('builds the published filter options with an all-states default', () => {
    const { publishedFilterOptions } = withComposable('en')
    const options = publishedFilterOptions.value
    expect(options[0].value).toBe('')
    expect(options.map((o) => o.value)).toEqual(['', 'true', 'false'])
  })

  it('resolves bilingual { ar, en } content to the active locale', () => {
    const ar = withComposable('ar')
    expect(ar.localized({ ar: 'المتطوعون', en: 'Volunteers' })).toBe('المتطوعون')
    const en = withComposable('en')
    expect(en.localized({ ar: 'المتطوعون', en: 'Volunteers' })).toBe('Volunteers')
  })

  it('returns a color label only for a known token', () => {
    const { colorLabel } = withComposable('en')
    expect(colorLabel('success')).toBe('Green')
    expect(colorLabel('not-a-token')).toBe('')
  })
})
