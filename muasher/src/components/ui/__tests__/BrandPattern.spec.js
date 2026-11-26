import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BrandPattern from '@/components/ui/BrandPattern.vue'

describe('BrandPattern', () => {
  it('renders the star pattern by default as a decorative element', () => {
    const wrapper = mount(BrandPattern)
    expect(wrapper.classes()).toEqual(['brand-pattern', 'brand-pattern--stars'])
    expect(wrapper.attributes('aria-hidden')).toBe('true')
    expect(wrapper.text()).toBe('')
  })

  it('renders the lattice pattern when asked', () => {
    const wrapper = mount(BrandPattern, { props: { variant: 'lattice' } })
    expect(wrapper.classes()).toContain('brand-pattern--lattice')
  })

  it('accepts only the two official patterns', () => {
    const { validator } = BrandPattern.props.variant
    expect(validator('stars')).toBe(true)
    expect(validator('lattice')).toBe(true)
    expect(validator('dots')).toBe(false)
  })
})
