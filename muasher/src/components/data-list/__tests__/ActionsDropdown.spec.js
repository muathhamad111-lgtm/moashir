import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ActionsDropdown from '@/components/data-list/ActionsDropdown.vue'
import { createTestGlobals } from '@/test-utils'

const actions = [
  { key: 'view', label: 'عرض', icon: 'fa-solid fa-eye' },
  { key: 'deactivate', label: 'تعطيل', icon: 'fa-solid fa-ban', variant: 'danger' },
  { key: 'archive', label: 'أرشفة', disabled: true },
]

function factory() {
  return mount(ActionsDropdown, { props: { actions }, global: createTestGlobals() })
}

describe('ActionsDropdown', () => {
  it('has an accessible icon-only trigger with the menu pattern', () => {
    const wrapper = factory()
    const trigger = wrapper.find('button')
    expect(trigger.attributes('aria-label')).toBe('الإجراءات')
    expect(trigger.attributes('aria-haspopup')).toBe('menu')
    expect(trigger.attributes('aria-expanded')).toBe('false')
  })

  it('opens the menu, emits select with the action key, and closes', async () => {
    const wrapper = factory()
    await wrapper.find('button').trigger('click')
    expect(wrapper.findAll('[role="menuitem"]')).toHaveLength(3)
    await wrapper.findAll('[role="menuitem"]')[0].trigger('click')
    expect(wrapper.emitted('select')[0]).toEqual(['view'])
    expect(wrapper.find('.dropdown__menu').exists()).toBe(false)
  })

  it('marks danger actions and disables disabled ones', async () => {
    const wrapper = factory()
    await wrapper.find('button').trigger('click')
    const items = wrapper.findAll('[role="menuitem"]')
    expect(items[1].classes()).toContain('is-danger')
    expect(items[2].attributes('disabled')).toBeDefined()
    await items[2].trigger('click')
    expect(wrapper.emitted('select')).toBeUndefined()
  })
})
