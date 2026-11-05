import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NotificationItem from '@/components/notifications/NotificationItem.vue'
import { createTestGlobals } from '@/test-utils'

const notification = {
  id: 1,
  code: 'ACC-07',
  module: 'ACC',
  category: 'action_required',
  title: { ar: 'طلب استكمال من المقيم', en: 'Completion requested' },
  body: { ar: 'يرجى استكمال المتطلبات.', en: 'Please complete the requirements.' },
  link: '/assessment/accredited/form/2041',
  isRead: false,
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
    expect(wrapper.classes()).toContain('notification-item--action_required')
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
      props: { notification: { ...notification, isRead: true } },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(wrapper.find('.notification-item__title').text()).toBe('Completion requested')
    expect(wrapper.find('.notification-item__dot').exists()).toBe(false)
  })

  it('isolates the references inside the server text', () => {
    const wrapper = mount(NotificationItem, {
      props: {
        notification: {
          ...notification,
          title: { ar: 'ردّ فريق منصة مؤشر على استفساركم CNT-2026-000052', en: 'Reply' },
        },
      },
      global: createTestGlobals(),
    })
    const reference = wrapper.find('.notification-item__title bdi.reference-text')
    expect(reference.text()).toBe('CNT-2026-000052')
    expect(reference.attributes('dir')).toBe('ltr')
  })
})
