import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import { REMOTE_SEARCH_DELAY_MS } from '@/constants/dropdown'

const STAFF = [
  { value: 11, label: 'أمين الجائزة' },
  { value: 12, label: 'مسؤول الفرز' },
  { value: 13, label: 'مقيّم أولي' },
  { value: 14, label: 'مدقق الجودة' },
]

function mountCombobox(props = {}, locale = 'ar') {
  return mount(AppCombobox, {
    props: { label: 'أعضاء فريق الدورة', options: STAFF, modelValue: null, ...props },
    attachTo: document.body,
    global: createTestGlobals({ locale }),
  })
}

function input(wrapper) {
  return wrapper.find('input[role="combobox"]')
}

function optionLabels(wrapper) {
  return wrapper.findAll('[role="option"] .dropdown-option__label').map((label) => label.text())
}

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('AppCombobox', () => {
  it('is an editable combobox with a list autocomplete that does not open on focus', async () => {
    const wrapper = mountCombobox({ hint: 'اختر من الموظفين المؤهلين', required: true })
    const field = input(wrapper)
    expect(field.attributes('aria-autocomplete')).toBe('list')
    expect(field.attributes('aria-expanded')).toBe('false')
    expect(field.attributes('aria-required')).toBe('true')
    expect(field.attributes('aria-describedby')).toBe(wrapper.find('.field__hint').attributes('id'))
    expect(wrapper.find(`label[for="${field.attributes('id')}"]`).text()).toBe('أعضاء فريق الدورة')
    await field.trigger('focus')
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    await field.trigger('keydown', { key: 'ArrowDown' })
    expect(field.attributes('aria-expanded')).toBe('true')
    expect(field.attributes('aria-controls')).toBe(
      wrapper.find('[role="listbox"]').attributes('id'),
    )
    expect(field.attributes('aria-activedescendant')).toBe(
      wrapper.find('.dropdown-option.is-active').attributes('id'),
    )
  })

  it('filters while typing with Arabic normalisation and reports the result count', async () => {
    const wrapper = mountCombobox()
    await input(wrapper).setValue('مقيم')
    expect(optionLabels(wrapper)).toEqual(['مقيّم أولي'])
    expect(wrapper.find('.dropdown-listbox__count').text()).toBe('نتيجة واحدة')
    await input(wrapper).setValue('غير موجود')
    expect(wrapper.find('[role="status"]').text()).toBe('لا توجد نتائج مطابقة لـ «غير موجود»')
  })

  it('selects a single value with the arrows and Enter and keeps its label visible', async () => {
    const wrapper = mountCombobox()
    await input(wrapper).trigger('keydown', { key: 'ArrowDown' })
    await input(wrapper).trigger('keydown', { key: 'ArrowDown' })
    await input(wrapper).trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')).toEqual([[12]])
    await wrapper.setProps({ modelValue: 12 })
    expect(input(wrapper).element.value).toBe('مسؤول الفرز')
    await wrapper.find('.dropdown-combobox').trigger('click')
    expect(input(wrapper).element.value).toBe('مسؤول الفرز')
    expect(wrapper.find('.dropdown-option.is-selected').text()).toContain('مسؤول الفرز')
  })

  it('commits the active option on Tab for a single value', async () => {
    const wrapper = mountCombobox()
    await input(wrapper).trigger('keydown', { key: 'ArrowDown' })
    await input(wrapper).trigger('keydown', { key: 'Tab' })
    expect(wrapper.emitted('update:modelValue')).toEqual([[11]])
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
  })

  it('closes and clears the query on Escape without bubbling, then lets the next Escape reach the dialog', async () => {
    const outer = vi.fn()
    document.body.addEventListener('keydown', outer)
    const wrapper = mountCombobox()
    await input(wrapper).setValue('مدقق')
    await input(wrapper).trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    expect(input(wrapper).element.value).toBe('')
    expect(outer).not.toHaveBeenCalled()
    await input(wrapper).trigger('keydown', { key: 'Escape' })
    expect(outer).toHaveBeenCalledTimes(1)
    document.body.removeEventListener('keydown', outer)
  })

  it('keeps the list open for multiple values, shows removable tags and removes the last with Backspace', async () => {
    const wrapper = mountCombobox({ multiple: true, modelValue: [11] })
    expect(wrapper.find('.dropdown-tag').text()).toContain('أمين الجائزة')
    expect(wrapper.find('.dropdown-tag__remove').attributes('aria-label')).toBe(
      'إزالة أمين الجائزة',
    )
    await input(wrapper).trigger('keydown', { key: 'ArrowDown' })
    expect(wrapper.find('[role="listbox"]').attributes('aria-multiselectable')).toBe('true')
    await wrapper.findAll('[role="option"]')[2].trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[[11, 13]]])
    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
    await input(wrapper).trigger('keydown', { key: 'Backspace' })
    expect(wrapper.emitted('update:modelValue')[1]).toEqual([[]])
    await wrapper.find('.dropdown-tag__remove').trigger('click')
    expect(wrapper.emitted('update:modelValue')[2]).toEqual([[]])
  })

  it('debounces the remote search and keeps the chosen label when the results change', async () => {
    vi.useFakeTimers()
    const wrapper = mountCombobox({ remote: true })
    await input(wrapper).setValue('مس')
    expect(wrapper.emitted('search')).toBeUndefined()
    vi.advanceTimersByTime(REMOTE_SEARCH_DELAY_MS)
    expect(wrapper.emitted('search')).toEqual([['مس']])
    await wrapper.findAll('[role="option"]')[1].trigger('click')
    await wrapper.setProps({ modelValue: 12, options: [{ value: 99, label: 'مقيّم آخر' }] })
    expect(input(wrapper).element.value).toBe('مسؤول الفرز')
  })

  it('shows the loading state and the error with aria-invalid', async () => {
    const wrapper = mountCombobox({ loading: true, error: 'هذا الحقل مطلوب' }, 'en')
    expect(input(wrapper).attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('.field__error').text()).toBe('هذا الحقل مطلوب')
    await wrapper.find('.dropdown-combobox').trigger('click')
    expect(wrapper.find('[role="status"]').text()).toBe('Loading options…')
  })
})

describe('AppCombobox below 768 px', () => {
  it('opens a sheet with its own search field, tags and a done button', async () => {
    stubMatchMedia({ width: 390 })
    const wrapper = mountCombobox({ multiple: true, modelValue: [11, 12] }, 'en')
    expect(input(wrapper).attributes('readonly')).toBeDefined()
    expect(input(wrapper).attributes('aria-haspopup')).toBe('dialog')
    await wrapper.find('.dropdown-combobox').trigger('click')
    await flushPromises()
    const search = wrapper.find('.dropdown-sheet__search-input')
    expect(document.activeElement).toBe(search.element)
    expect(search.attributes('role')).toBe('combobox')
    expect(search.attributes('aria-label')).toBe('Search أعضاء فريق الدورة')
    expect(wrapper.findAll('.dropdown-sheet__tags .dropdown-tag')).toHaveLength(2)
    await search.setValue('مدقق')
    await search.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')).toEqual([[[11, 12, 14]]])
    const done = wrapper.find('.dropdown-sheet__done')
    expect(done.text()).toBe('Done — 2 selected')
    await done.trigger('click')
    await nextTick()
    await flushPromises()
    expect(wrapper.find('.dropdown-sheet').exists()).toBe(false)
    expect(document.activeElement).toBe(input(wrapper).element)
  })
})
