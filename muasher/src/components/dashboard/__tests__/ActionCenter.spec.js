import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ActionCenter from '@/components/dashboard/ActionCenter.vue'
import { createTestGlobals } from '@/test-utils'

function localized(content) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content.ar || content.en || ''
}

const actions = [
  {
    id: 'a1',
    text: { ar: 'يوجد 12 طلب بحاجة إلى إسناد', en: '12 requests need assignment' },
    count: 12,
    to: 'requests.index',
    icon: 'fa-solid fa-inbox',
    severity: 'warning',
  },
  {
    id: 'a2',
    text: { ar: 'أكمل رفع الشواهد', en: 'Complete uploading evidence' },
    count: null,
    to: 'assessment.accredited',
    icon: 'fa-solid fa-paperclip',
    severity: 'info',
  },
]

function factory(props = {}) {
  return mount(ActionCenter, {
    props: { actions, localized, ...props },
    global: { plugins: createTestGlobals({ locale: 'ar' }).plugins },
  })
}

describe('ActionCenter', () => {
  it('renders one item per action with its localized text', () => {
    const wrapper = factory()
    const items = wrapper.findAll('.action-center__item')
    expect(items).toHaveLength(2)
    expect(items[0].find('.action-center__item-text').text()).toBe('يوجد 12 طلب بحاجة إلى إسناد')
  })

  it('links each action to its route by name', () => {
    const wrapper = factory()
    const links = wrapper.findAll('.action-center__link')
    expect(links[0].attributes('href')).toContain('/requests')
  })

  it('renders a count when present and hides it when null', () => {
    const wrapper = factory()
    const items = wrapper.findAll('.action-center__item')
    expect(items[0].find('.action-center__item-count').exists()).toBe(true)
    expect(items[0].find('.action-center__item-count').text()).toBe('12')
    expect(items[1].find('.action-center__item-count').exists()).toBe(false)
  })

  it('applies the severity modifier class', () => {
    const wrapper = factory()
    const items = wrapper.findAll('.action-center__item')
    expect(items[0].classes()).toContain('action-center__item--warning')
    expect(items[1].classes()).toContain('action-center__item--info')
  })

  it('shows a skeleton while loading', () => {
    const wrapper = factory({ loading: true })
    expect(wrapper.find('.action-center__loading').exists()).toBe(true)
    expect(wrapper.find('.action-center__list').exists()).toBe(false)
  })

  it('shows the empty state when there are no actions', () => {
    const wrapper = factory({ actions: [] })
    expect(wrapper.find('.empty-state').exists()).toBe(true)
    expect(wrapper.find('.action-center__list').exists()).toBe(false)
  })
})
