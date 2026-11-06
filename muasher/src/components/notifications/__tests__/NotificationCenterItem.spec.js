import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NotificationCenterItem from '@/components/notifications/NotificationCenterItem.vue'
import { createTestGlobals } from '@/test-utils'

const notification = {
  id: '5e1f0c2a-0000-4000-8000-000000000001',
  code: 'CNT-02',
  module: 'CNT',
  category: 'informational',
  title: { ar: 'ردّ فريق منصة مؤشر على استفسارك', en: 'The Moashir team replied' },
  body: { ar: 'أضاف الفريق رداً جديداً.', en: 'The team added a new reply.' },
  link: '/messages',
  isRead: false,
  createdAt: new Date().toISOString(),
}

function factory(props = {}, locale = 'ar') {
  return mount(NotificationCenterItem, {
    props: { notification, ...props },
    global: createTestGlobals({ locale }),
  })
}

describe('NotificationCenterItem', () => {
  it('renders the localized title, body, module icon and the unread marker as text', () => {
    const wrapper = factory()
    expect(wrapper.find('.notification-center-item__title').text()).toBe(notification.title.ar)
    expect(wrapper.find('.notification-center-item__body').text()).toBe(notification.body.ar)
    expect(wrapper.find('.notification-center-item__icon svg').attributes('data-icon')).toBe(
      'envelope',
    )
    expect(wrapper.classes()).toContain('is-unread')
    expect(wrapper.find('.notification-center-item__sr').text()).toBe('غير مقروء')
  })

  it('emits open from the title link and archive from its button', async () => {
    const wrapper = factory()
    await wrapper.find('.notification-center-item__title').trigger('click')
    expect(wrapper.emitted('open')[0][0].id).toBe(notification.id)
    await wrapper.find('.notification-center-item__archive').trigger('click')
    expect(wrapper.emitted('archive')[0][0].id).toBe(notification.id)
  })

  it('names the archive button after the notification and disables it while busy', () => {
    const wrapper = factory({ busy: true }, 'en')
    const archive = wrapper.find('.notification-center-item__archive')
    expect(archive.attributes('aria-label')).toBe(
      'Archive the notification: The Moashir team replied',
    )
    expect(archive.attributes('disabled')).toBeDefined()
  })

  it('shows an older notification with its date and no unread marker once read', () => {
    const wrapper = factory({
      notification: { ...notification, isRead: true, createdAt: '2026-01-05T08:00:00+03:00' },
    })
    expect(wrapper.find('.notification-center-item__time').text()).toBe('2026-01-05')
    expect(wrapper.find('.notification-center-item__unread').exists()).toBe(false)
  })
})
