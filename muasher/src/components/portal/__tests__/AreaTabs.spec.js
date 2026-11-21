import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AreaTabs from '@/components/portal/AreaTabs.vue'
import { createTestGlobals } from '@/test-utils'

const TABS = [
  { value: 'all', label: 'الكل', count: 6 },
  { value: 'unread', label: 'غير المقروءة', count: 2 },
  { value: 'archived', label: 'المؤرشفة', count: null },
]

function factory(modelValue = 'all') {
  return mount(AreaTabs, {
    props: { tabs: TABS, modelValue, label: 'تصفية', panelId: 'panel' },
    global: createTestGlobals(),
    attachTo: document.body,
  })
}

describe('AreaTabs', () => {
  it('renders an accessible tablist with counts and a roving tabindex', () => {
    const wrapper = factory()
    expect(wrapper.attributes('role')).toBe('tablist')
    expect(wrapper.attributes('aria-label')).toBe('تصفية')
    const tabs = wrapper.findAll('[role="tab"]')
    expect(tabs.map((tab) => tab.attributes('aria-selected'))).toEqual(['true', 'false', 'false'])
    expect(tabs.map((tab) => tab.attributes('tabindex'))).toEqual(['0', '-1', '-1'])
    expect(tabs[0].attributes('aria-controls')).toBe('panel')
    expect(tabs[0].find('.area-tabs__count').text()).toBe('6')
    expect(tabs[2].find('.area-tabs__count').exists()).toBe(false)
    wrapper.unmount()
  })

  it('selects on click and with Home / End', async () => {
    const wrapper = factory()
    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['unread'])
    await wrapper.findAll('[role="tab"]')[0].trigger('keydown', { key: 'End' })
    expect(wrapper.emitted('update:modelValue')[1]).toEqual(['archived'])
    await wrapper.findAll('[role="tab"]')[0].trigger('keydown', { key: 'Home' })
    expect(wrapper.emitted('update:modelValue')[2]).toEqual(['all'])
    wrapper.unmount()
  })

  it('follows the reading direction with the arrow keys', async () => {
    document.documentElement.dir = 'rtl'
    const wrapper = factory()
    await wrapper.findAll('[role="tab"]')[0].trigger('keydown', { key: 'ArrowLeft' })
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['unread'])
    document.documentElement.dir = 'ltr'
    await wrapper.findAll('[role="tab"]')[0].trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')[1]).toEqual(['unread'])
    wrapper.unmount()
  })
})
