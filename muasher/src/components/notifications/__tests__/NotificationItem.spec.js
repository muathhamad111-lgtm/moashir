import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NotificationItem from '@/components/notifications/NotificationItem.vue'
import { createTestGlobals } from '@/test-utils'

const notification = {
  id: 1,
  code: 'ACC-07',
  type: 'action',
  title: { ar: 'طلب استكمال من المقيم', en: 'Completion requested' },
  body: { ar: 'يرجى استكمال المتطلبات.', en: 'Please complete the requirements.' },
  link: '/assessment/accredited/form/2041',
  read: false,
  createdAt: '2026-07-07T08:40:00+03:00',
}

describe('NotificationItem', () => {
  it('renders the localized title/body and deep link', () => {
    const wrapper = mount(NotificationItem, {
      props: { notification },
      global: createTestGlobals(),
    })
    expect(wrapper.find('.notification-item__title').text()).toBe('طلب استكمال من المقيم')
    expect(wrapper.find('.notification-item__body').text()).toBe('يرجى استكمال المتطلبات.')
    expect(wrapper.attributes('href')).toBe('/assessment/accredited/form/2041')
    expect(wrapper.classes()).toContain('notification-item--action')
  })

  it('shows an unread indicator with an accessible name and emits open on click', async () => {
    const wrapper = mount(NotificationItem, {
      props: { notification },
      global: createTestGlobals(),
    })
    const dot = wrapper.find('.notification-item__dot')
    expect(dot.attributes('aria-label')).toBe('غير مقروء')
    expect(wrapper.classes()).toContain('is-unread')
    await wrapper.trigger('click')
    expect(wrapper.emitted('open')[0][0].id).toBe(1)
  })

  it('renders the English content when locale is en, without the unread dot when read', () => {
    const wrapper = mount(NotificationItem, {
      props: { notification: { ...notification, read: true } },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(wrapper.find('.notification-item__title').text()).toBe('Completion requested')
    expect(wrapper.find('.notification-item__dot').exists()).toBe(false)
  })
})
