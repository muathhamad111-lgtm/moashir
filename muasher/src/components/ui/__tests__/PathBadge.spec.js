import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PathBadge from '@/components/ui/PathBadge.vue'
import { createTestGlobals } from '@/test-utils'

function render(component, props, locale = 'en') {
  return mount(component, { props, global: { plugins: createTestGlobals({ locale }).plugins } })
}

describe('PathBadge', () => {
  it('shows the served label with the colour and icon of the path', () => {
    const wrapper = render(PathBadge, {
      path: 'advanced',
      label: { ar: 'التقييم المتقدم (الجائزة)', en: 'Advanced assessment (Award)' },
    })
    expect(wrapper.classes()).toContain('path-badge--advanced')
    expect(wrapper.text()).toBe('Advanced assessment (Award)')
    expect(wrapper.find('svg').attributes('data-icon')).toBe('trophy')
    expect(wrapper.find('svg').attributes('aria-hidden')).toBe('true')
  })

  it('falls back to the translated label and a neutral style for an unknown path', () => {
    expect(render(PathBadge, { path: 'accredited' }, 'ar').text()).toBe('التقييم المعتمد')
    const unknown = render(PathBadge, { path: 'leaders' })
    expect(unknown.classes()).toContain('path-badge--other')
    expect(unknown.text()).toBe('leaders')
  })
})
