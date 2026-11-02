import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import { createTestGlobals } from '@/test-utils'

describe('ListToolbar', () => {
  it('renders an accessible search input and emits update:search', async () => {
    const wrapper = mount(ListToolbar, {
      props: { search: '' },
      global: createTestGlobals(),
    })
    const input = wrapper.find('input[type="search"]')
    expect(input.attributes('aria-label')).toBe('بحث')
    await input.setValue('جمعية')
    expect(wrapper.emitted('update:search')[0]).toEqual(['جمعية'])
  })

  it('renders filters and actions slots in their regions', () => {
    const wrapper = mount(ListToolbar, {
      props: { search: '' },
      slots: { filters: '<span class="f">filter</span>', actions: '<span class="a">action</span>' },
      global: createTestGlobals(),
    })
    expect(wrapper.find('.toolbar__filters .f').exists()).toBe(true)
    expect(wrapper.find('.toolbar__actions .a').exists()).toBe(true)
  })
})
