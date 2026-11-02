import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { createTestGlobals } from '@/test-utils'

describe('ErrorState', () => {
  it('renders as an alert with the localized default message and retry button', () => {
    const wrapper = mount(ErrorState, { global: createTestGlobals() })
    expect(wrapper.attributes('role')).toBe('alert')
    expect(wrapper.find('.error-state__message').text()).toBe('تعذّر تحميل البيانات')
    expect(wrapper.find('.error-state__retry button').text()).toContain('إعادة المحاولة')
  })

  it('shows a custom message and emits retry', async () => {
    const wrapper = mount(ErrorState, {
      props: { message: 'تعذر الاتصال بالخادم' },
      global: createTestGlobals(),
    })
    expect(wrapper.find('.error-state__message').text()).toBe('تعذر الاتصال بالخادم')
    await wrapper.find('.error-state__retry button').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })
})
