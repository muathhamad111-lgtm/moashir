import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PathBadge from '@/components/ui/PathBadge.vue'
import RequestPathSwitch from '@/components/requests/RequestPathSwitch.vue'
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

describe('RequestPathSwitch', () => {
  it('links every path with its count and marks the current one', () => {
    const wrapper = render(RequestPathSwitch, {
      entries: [
        { path: 'accredited', pathLabel: null, countLabel: '3 awaiting assignment' },
        { path: 'advanced', pathLabel: null, countLabel: '' },
      ],
      current: 'advanced',
      routeName: 'requests.index',
      label: 'Request queue paths',
    })
    const links = wrapper.findAll('a')
    expect(wrapper.find('nav').attributes('aria-label')).toBe('Request queue paths')
    expect(links[0].attributes('href')).toBe('/admin/requests?path=accredited')
    expect(links[0].text()).toContain('3 awaiting assignment')
    expect(links[1].attributes('aria-current')).toBe('page')
    expect(links[0].attributes('aria-current')).toBeUndefined()
  })
})
