import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DropdownOption from '@/components/ui/DropdownOption.vue'
import DropdownListbox from '@/components/ui/DropdownListbox.vue'
import { createTestGlobals } from '@/test-utils'

describe('DropdownOption', () => {
  it('renders the label, an isolated description, a decorative icon and a reserved check slot', () => {
    const wrapper = mount(DropdownOption, {
      props: { label: 'مخطط دائري', description: 'pie-chart', icon: 'pie-chart' },
    })
    expect(wrapper.element.tagName).toBe('LI')
    expect(wrapper.find('.dropdown-option__label').text()).toBe('مخطط دائري')
    expect(wrapper.find('bdi.dropdown-option__description').text()).toBe('pie-chart')
    expect(wrapper.find('.dropdown-option__icon').attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('.dropdown-option__check').exists()).toBe(true)
    expect(wrapper.find('.dropdown-option__check svg').exists()).toBe(false)
  })

  it('shows the active, selected, disabled and danger states', () => {
    const wrapper = mount(DropdownOption, {
      props: { label: 'حذف', active: true, selected: true, disabled: true, danger: true },
    })
    expect(wrapper.classes()).toEqual(
      expect.arrayContaining(['is-active', 'is-selected', 'is-disabled', 'is-danger']),
    )
    expect(wrapper.find('.dropdown-option__check svg').attributes('data-icon')).toBe('check')
  })

  it('renders as a menu item button without a check slot', () => {
    const wrapper = mount(DropdownOption, {
      props: { label: 'تعديل', tag: 'button', checkable: false },
      attrs: { type: 'button', role: 'menuitem' },
    })
    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.attributes('role')).toBe('menuitem')
    expect(wrapper.find('.dropdown-option__check').exists()).toBe(false)
  })
})

describe('DropdownListbox', () => {
  function mountListbox(props) {
    return mount(DropdownListbox, {
      props: {
        id: 'list',
        label: 'المنطقة',
        optionId: (index) => `o-${index}`,
        options: [],
        ...props,
      },
      global: createTestGlobals({ locale: 'en' }),
    })
  }

  it('keeps the status messages outside the listbox', () => {
    const wrapper = mountListbox({ query: 'xyz' })
    expect(wrapper.find('[role="listbox"] [role="status"]').exists()).toBe(false)
    expect(wrapper.find('[role="status"]').text()).toBe('No results match “xyz”')
  })

  it('counts the results of a search and marks the selected values', () => {
    const wrapper = mountListbox({
      query: 'ri',
      options: [
        { value: 1, label: 'Riyadh' },
        { value: 2, label: 'Diriyah' },
      ],
      selectedValues: ['2'],
    })
    expect(wrapper.find('.dropdown-listbox__count').text()).toBe('2 results')
    expect(
      wrapper.findAll('[role="option"]').map((option) => option.attributes('aria-selected')),
    ).toEqual(['false', 'true'])
  })
})
