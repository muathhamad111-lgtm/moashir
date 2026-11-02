import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AppFooter from '@/components/layout/AppFooter.vue'
import { createTestGlobals } from '@/test-utils'

describe('AppFooter', () => {
  it('renders brand, tagline, copyright with the year, and the developed-by link', () => {
    const wrapper = mount(AppFooter, { global: createTestGlobals() })
    expect(wrapper.find('.footer__logo').attributes('alt')).toBe('مؤشر')
    expect(wrapper.find('.footer__tagline').text()).toContain('نموذج التميز')
    expect(wrapper.find('.footer__copyright').text()).toContain(String(new Date().getFullYear()))
    const developed = wrapper.find('.footer__developed-link')
    expect(developed.text()).toContain('شركة التحول التقني')
    expect(developed.attributes('href')).toBe('https://tts.sa')
  })

  it('is a footer landmark', () => {
    const wrapper = mount(AppFooter, { global: createTestGlobals() })
    expect(wrapper.element.tagName).toBe('FOOTER')
  })
})
