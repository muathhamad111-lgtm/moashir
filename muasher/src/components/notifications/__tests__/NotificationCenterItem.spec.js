import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NotificationCenterItem from '@/components/notifications/NotificationCenterItem.vue'
import { createTestGlobals } from '@/test-utils'

const notification = {
  id: 15,
  code: 'ACC-05',
  type: 'action',
  title: { ar: 'إسناد طلب تقييم معتمد إليكم', en: 'Accredited assessment assigned to you' },
  body: { ar: 'تم إسناد طلب تقييم معتمد جديد إليكم.', en: 'A new request has been assigned to you.' },
  link: '/admin/requests/9108/review',
  read: false,
  createdAt: '2026-07-06T11:20:00+03:00',
}

function mountItem(overrides = {}, opts = {}) {
  return mount(NotificationCenterItem, {
    props: { notification: { ...notification, ...overrides } },
    global: createTestGlobals(opts),
  })
}

describe('NotificationCenterItem', () => {
  it('renders the type badge label (text, not color-only), code, title and body', () => {
    const wrapper = mountItem()
    expect(wrapper.find('.badge').text()).toBe('يتطلب إجراء')
    expect(wrapper.find('.notification-center-item__code').text()).toBe('ACC-05')
    expect(wrapper.find('.notification-center-item__title').text()).toBe(
      'إسناد طلب تقييم معتمد إليكم',
    )
    expect(wrapper.find('.notification-center-item__body').text()).toContain('تم إسناد')
    expect(wrapper.classes()).toContain('notification-center-item--action')
    expect(wrapper.classes()).toContain('is-unread')
  })

  it('deep-links the title to the record and emits open on click', async () => {
    const wrapper = mountItem()
    const title = wrapper.find('.notification-center-item__title')
    expect(title.attributes('href')).toBe('/admin/requests/9108/review')
    await title.trigger('click')
    expect(wrapper.emitted('open')[0][0].id).toBe(15)
  })

  it('emits mark-read and audit from the action buttons', async () => {
    const wrapper = mountItem()
    const buttons = wrapper.findAll('.notification-center-item__action')
    await buttons[0].trigger('click')
    expect(wrapper.emitted('mark-read')[0][0]).toBe(15)
    await buttons[1].trigger('click')
    expect(wrapper.emitted('audit')[0][0].id).toBe(15)
  })

  it('hides the mark-read control and the unread marker when the item is read', () => {
    const wrapper = mountItem({ read: true })
    expect(wrapper.find('.notification-center-item__unread').exists()).toBe(false)
    expect(wrapper.findAll('.notification-center-item__action')).toHaveLength(1)
  })

  it('disables the mark-read button while busy', () => {
    const wrapper = mount(NotificationCenterItem, {
      props: { notification, busy: true },
      global: createTestGlobals(),
    })
    const markRead = wrapper.findAll('.notification-center-item__action')[0]
    expect(markRead.attributes('disabled')).toBeDefined()
  })

  it('renders English content under the en locale', () => {
    const wrapper = mountItem({}, { locale: 'en' })
    expect(wrapper.find('.notification-center-item__title').text()).toBe(
      'Accredited assessment assigned to you',
    )
    expect(wrapper.find('.badge').text()).toBe('Action required')
  })
})
