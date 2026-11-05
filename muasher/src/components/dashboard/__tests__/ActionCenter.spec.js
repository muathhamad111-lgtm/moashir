import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ActionCenter from '@/components/dashboard/ActionCenter.vue'
import { createTestGlobals } from '@/test-utils'

const items = [
  {
    key: 'assign_requests',
    count: 3,
    severity: 'warning',
    link: '/admin/requests?status=submitted%2Cawaiting_review_assignment',
    dueAt: null,
    context: null,
  },
  {
    key: 'answer_contact_messages',
    count: 2,
    severity: 'danger',
    link: '/admin/contact-messages?status=new',
    dueAt: null,
    context: null,
  },
  { key: 'future_key', count: 1, severity: 'unknown', link: 'https://example.sa', dueAt: null },
]

function mountWith(props, locale = 'ar') {
  return mount(ActionCenter, { props, global: createTestGlobals({ locale }) })
}

describe('ActionCenter (Action Center contract)', () => {
  it('labels each item by its key and shows the counts with an accessible total', () => {
    const wrapper = mountWith({ items })
    const rows = wrapper.findAll('.action-center__item')
    expect(rows).toHaveLength(3)
    expect(rows[0].text()).toContain('طلبات بانتظار إسناد مقيّم')
    expect(rows[1].text()).toContain('رسائل تواصل جديدة بانتظار المتابعة')
    expect(rows[0].find('.action-center__item-count').text()).toBe('3')
    expect(wrapper.find('.action-center__count').text()).toBe('6')
    expect(wrapper.find('.action-center__count').attributes('aria-label')).toContain('6')
  })

  it('links internal paths only and keeps unknown keys readable', () => {
    const wrapper = mountWith({ items }, 'en')
    const links = wrapper.findAll('a.action-center__link')
    expect(links.map((link) => link.attributes('href'))).toEqual([
      '/admin/requests?status=submitted%2Cawaiting_review_assignment',
      '/admin/contact-messages?status=new',
    ])
    expect(wrapper.findAll('.action-center__item')[2].text()).toContain('future_key')
  })

  it('applies a known severity tone and falls back to info', () => {
    const rows = mountWith({ items }).findAll('.action-center__item')
    expect(rows[0].classes()).toContain('action-center__item--warning')
    expect(rows[1].classes()).toContain('action-center__item--danger')
    expect(rows[2].classes()).toContain('action-center__item--info')
  })

  it('shows the due time when the item has one', () => {
    const due = new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString()
    const wrapper = mountWith({ items: [{ ...items[0], dueAt: due }] }, 'en')
    expect(wrapper.find('.action-center__due').text()).toContain('Due')
  })

  it('shows a skeleton while loading and an empty state without items', () => {
    expect(mountWith({ items: [], loading: true }).find('.skeleton').exists()).toBe(true)
    expect(mountWith({ items: [] }, 'en').text()).toContain('No')
  })
})
