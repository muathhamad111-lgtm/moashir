import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchSelect from '@/components/form/SearchSelect.vue'
import { createTestGlobals } from '@/test-utils'

const options = [
  { value: 'riyadh', label: 'الرياض' },
  { value: 'makkah', label: 'مكة المكرمة' },
  { value: 'eastern', label: 'المنطقة الشرقية' },
]

function factory(props = {}) {
  return mount(SearchSelect, {
    props: { modelValue: null, options, placeholder: 'اختر المنطقة', ...props },
    global: createTestGlobals(),
  })
}

describe('SearchSelect', () => {
  it('opens on focus and filters options by the typed query', async () => {
    const wrapper = factory()
    const input = wrapper.find('.select2-search__input')
    await input.trigger('focus')
    expect(wrapper.classes()).toContain('is-open')
    expect(wrapper.findAll('.select2-search__option')).toHaveLength(3)
    await input.setValue('مكة')
    expect(wrapper.findAll('.select2-search__option')).toHaveLength(1)
    expect(wrapper.find('.select2-search__option').text()).toBe('مكة المكرمة')
  })

  it('selects a single value, emits it, and closes', async () => {
    const wrapper = factory()
    await wrapper.find('.select2-search__input').trigger('focus')
    await wrapper.findAll('.select2-search__option')[0].trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['riyadh'])
    expect(wrapper.classes()).not.toContain('is-open')
  })

  it('renders tags in multiple mode and removes them (button + Backspace)', async () => {
    const wrapper = factory({ modelValue: ['riyadh', 'makkah'], multiple: true })
    expect(wrapper.findAll('.select2-search__tag')).toHaveLength(2)
    await wrapper.find('.select2-search__tag-remove').trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([['makkah']])
    await wrapper.find('.select2-search__input').trigger('keydown', { key: 'Backspace' })
    expect(wrapper.emitted('update:modelValue')[1]).toEqual([['riyadh']])
  })

  it('navigates with the keyboard and selects with Enter', async () => {
    const wrapper = factory()
    const input = wrapper.find('.select2-search__input')
    await input.trigger('focus')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['makkah'])
  })

  it('emits search for remote mode and shows the loading state', async () => {
    const wrapper = factory({ remote: true, loading: true })
    const input = wrapper.find('.select2-search__input')
    await input.trigger('focus')
    await input.setValue('جمعية')
    expect(wrapper.emitted('search')[0]).toEqual(['جمعية'])
    expect(wrapper.find('.select2-search__empty').text()).toBe('جارٍ تحميل الخيارات…')
  })

  it('shows the empty message when nothing matches and respects disabled', async () => {
    const wrapper = factory()
    const input = wrapper.find('.select2-search__input')
    await input.trigger('focus')
    await input.setValue('لا يوجد')
    expect(wrapper.find('.select2-search__empty').text()).toBe('لا توجد نتائج مطابقة')

    const disabled = factory({ disabled: true })
    expect(disabled.classes()).toContain('is-disabled')
    expect(disabled.find('.select2-search__input').attributes('disabled')).toBeDefined()
  })

  it('implements the combobox pattern (no jQuery, plain Vue)', async () => {
    const wrapper = factory()
    const input = wrapper.find('.select2-search__input')
    expect(input.attributes('role')).toBe('combobox')
    expect(input.attributes('aria-expanded')).toBe('false')
    await input.trigger('focus')
    expect(input.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
  })
})
