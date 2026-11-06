import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EmptyState from '@/components/feedback/EmptyState.vue'

describe('EmptyState', () => {
  it('renders title, message, and default art as a status region', () => {
    const wrapper = mount(EmptyState, {
      props: { title: 'لا توجد إشعارات', message: 'ستظهر الإشعارات الجديدة هنا' },
    })
    expect(wrapper.attributes('role')).toBe('status')
    expect(wrapper.find('.empty-state__title').text()).toBe('لا توجد إشعارات')
    expect(wrapper.find('.empty-state__message').text()).toBe('ستظهر الإشعارات الجديدة هنا')
    expect(wrapper.find('.empty-state__art svg.app-icon').exists()).toBe(true)
  })

  it('renders the action slot and a custom art slot', () => {
    const wrapper = mount(EmptyState, {
      props: { title: 'فارغ' },
      slots: { action: '<button class="cta">ابدأ</button>', art: '<svg class="custom-art"></svg>' },
    })
    expect(wrapper.find('.empty-state__action .cta').exists()).toBe(true)
    expect(wrapper.find('.custom-art').exists()).toBe(true)
  })
})
