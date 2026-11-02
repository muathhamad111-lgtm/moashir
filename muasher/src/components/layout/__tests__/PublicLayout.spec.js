import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PublicLayout from '@/components/layout/PublicLayout.vue'
import { createTestGlobals } from '@/test-utils'

describe('PublicLayout', () => {
  it('wraps the slot in a main region between navbar and footer', () => {
    const wrapper = mount(PublicLayout, {
      slots: { default: '<p class="page">الصفحة الرئيسية</p>' },
      global: createTestGlobals(),
    })
    expect(wrapper.find('.public-navbar').exists()).toBe(true)
    expect(wrapper.find('main.public-layout__content .page').text()).toBe('الصفحة الرئيسية')
    expect(wrapper.find('.footer').exists()).toBe(true)
  })
})
