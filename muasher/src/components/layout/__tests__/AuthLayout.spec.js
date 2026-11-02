import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { createTestGlobals } from '@/test-utils'

describe('AuthLayout', () => {
  it('renders the brand panel and the centered form card slot', () => {
    const wrapper = mount(AuthLayout, {
      slots: { default: '<form class="login-form"></form>' },
      global: createTestGlobals(),
    })
    const brand = wrapper.find('.auth-layout__brand')
    expect(brand.exists()).toBe(true)
    expect(brand.find('.auth-layout__logo').attributes('alt')).toBe('مؤشر')
    expect(wrapper.find('main .auth-layout__card .login-form').exists()).toBe(true)
  })

  it('offers locale/theme switchers and a back-to-home link', () => {
    const wrapper = mount(AuthLayout, { global: createTestGlobals() })
    expect(wrapper.find('.auth-layout__meta').text()).toContain('English')
    expect(wrapper.find('.auth-layout__link').text()).toBe('العودة إلى الرئيسية')
  })
})
