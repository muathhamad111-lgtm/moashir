import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NotificationPanel from '@/components/notifications/NotificationPanel.vue'
import { createTestGlobals } from '@/test-utils'

const items = [
  {
    id: 1,
    code: 'PAY-02',
    type: 'info',
    title: { ar: 'تم استلام الرسوم', en: 'Fee received' },
    body: { ar: 'تم فتح النموذج.', en: 'Form opened.' },
    link: '/assessment/accredited/form/2041',
    read: false,
    createdAt: '2026-07-06T21:05:00+03:00',
  },
]

function factory(props = {}) {
  return mount(NotificationPanel, {
    props: { items, status: 'success', unreadCount: 1, ...props },
    global: createTestGlobals(),
  })
}

describe('NotificationPanel', () => {
  it('renders items with a mark-all-read action and a view-all link', async () => {
    const wrapper = factory()
    expect(wrapper.findAll('.notification-item')).toHaveLength(1)
    expect(wrapper.find('.notification-panel__all').text()).toBe('عرض جميع الإشعارات')
    await wrapper.find('.notification-panel__header button').trigger('click')
    expect(wrapper.emitted('mark-all-read')).toHaveLength(1)
  })

  it('shows skeletons while loading and a branded empty state', () => {
    expect(factory({ status: 'loading' }).findAll('.skeleton').length).toBeGreaterThan(0)
    const empty = factory({ status: 'empty', items: [], unreadCount: 0 })
    expect(empty.find('.empty-state__title').text()).toBe('لا توجد إشعارات')
  })

  it('shows the error state and emits retry', async () => {
    const wrapper = factory({ status: 'error', items: [] })
    await wrapper.find('.error-state__retry button').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })
})
