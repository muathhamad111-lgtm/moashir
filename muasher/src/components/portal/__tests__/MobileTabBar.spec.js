import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MobileTabBar from '@/components/portal/MobileTabBar.vue'
import { createTestGlobals } from '@/test-utils'

async function at(path) {
  const globals = createTestGlobals()
  await globals.router.push(path)
  return mount(MobileTabBar, { global: globals })
}

describe('MobileTabBar', () => {
  it('offers journey, assessments, certificates and more', async () => {
    const wrapper = await at('/dashboard')
    expect(wrapper.findAll('.mobile-tab-bar__label').map((label) => label.text())).toEqual([
      'رحلتي',
      'تقييماتي',
      'شهاداتي',
      'المزيد',
    ])
    expect(wrapper.find('.is-active').attributes('aria-current')).toBe('page')
    expect(wrapper.find('.is-active').text()).toBe('رحلتي')
  })

  it('keeps «المزيد» active for payments, messages and notifications', async () => {
    for (const path of ['/payments', '/messages', '/notifications']) {
      const wrapper = await at(path)
      expect(wrapper.find('.is-active').text()).toBe('المزيد')
    }
  })
})
