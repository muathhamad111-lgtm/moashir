import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DropdownList from '@/components/ui/DropdownList.vue'

const options = [
  { value: 'draft', label: 'مسودة' },
  { value: 'submitted', label: 'مُرسل' },
  { value: 'completed', label: 'مكتمل' },
]

function factory(props = {}) {
  return mount(DropdownList, {
    props: { modelValue: null, options, placeholder: 'اختر…', ...props },
  })
}

describe('DropdownList', () => {
  it('shows the placeholder when nothing is selected and opens on click', async () => {
    const wrapper = factory()
    expect(wrapper.find('.dropdown__placeholder').text()).toBe('اختر…')
    expect(wrapper.find('.dropdown__menu').exists()).toBe(false)
    await wrapper.find('.dropdown__toggle').trigger('click')
    expect(wrapper.classes()).toContain('is-open')
    expect(wrapper.findAll('.dropdown__item')).toHaveLength(3)
  })

  it('emits update:modelValue and closes when an option is chosen', async () => {
    const wrapper = factory()
    await wrapper.find('.dropdown__toggle').trigger('click')
    await wrapper.findAll('.dropdown__item')[1].trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['submitted'])
    expect(wrapper.find('.dropdown__menu').exists()).toBe(false)
  })

  it('supports keyboard navigation: ArrowDown opens, moves highlight, Enter selects', async () => {
    const wrapper = factory()
    const toggle = wrapper.find('.dropdown__toggle')
    await toggle.trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.classes()).toContain('is-open')
    await toggle.trigger('keydown', { key: 'ArrowDown' })
    await toggle.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['submitted'])
  })

  it('exposes the listbox pattern and marks the selected option', async () => {
    const wrapper = factory({ modelValue: 'completed' })
    const toggle = wrapper.find('.dropdown__toggle')
    expect(toggle.attributes('aria-haspopup')).toBe('listbox')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    await toggle.trigger('click')
    const selected = wrapper.find('[aria-selected="true"]')
    expect(selected.text()).toBe('مكتمل')
    expect(selected.classes()).toContain('is-active')
  })

  it('does not open when disabled', async () => {
    const wrapper = factory({ disabled: true })
    await wrapper.find('.dropdown__toggle').trigger('click')
    expect(wrapper.find('.dropdown__menu').exists()).toBe(false)
  })
})
