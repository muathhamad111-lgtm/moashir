import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import { createTestGlobals } from '@/test-utils'

function mountPicker(props = {}) {
  return mount(BaseDatePicker, {
    props: { modelValue: '', label: 'تاريخ التأسيس', name: 'foundedOn', ...props },
    attachTo: document.body,
    global: { plugins: createTestGlobals().plugins },
  })
}

function segment(wrapper, key) {
  return wrapper.find(`.datepicker__segment--${key}`)
}

describe('BaseDatePicker', () => {
  it('renders day, month and year boxes labelled in the active language', () => {
    const wrapper = mountPicker()
    const placeholders = wrapper
      .findAll('.datepicker__segment')
      .map((input) => input.attributes('placeholder'))

    expect(placeholders).toEqual(['يوم', 'شهر', 'سنة'])
    expect(wrapper.find('label').attributes('for')).toBe(segment(wrapper, 'day').attributes('id'))
    expect(wrapper.find('[role="group"]').attributes('aria-label')).toBe('تاريخ التأسيس')
    expect(segment(wrapper, 'month').attributes('aria-label')).toBe('تاريخ التأسيس: الشهر')
    wrapper.unmount()
  })

  it('keeps the bounds and the field name on the calendar input', () => {
    const wrapper = mountPicker({ min: '1900-01-01', max: '2026-11-15', modelValue: '2015-03-21' })
    const native = wrapper.find('input[type="date"]')

    expect(native.attributes('min')).toBe('1900-01-01')
    expect(native.attributes('max')).toBe('2026-11-15')
    expect(native.attributes('name')).toBe('foundedOn')
    expect(native.element.value).toBe('2015-03-21')
    wrapper.unmount()
  })

  it('shows a stored date in the three boxes', () => {
    const wrapper = mountPicker({ modelValue: '2015-03-21' })

    expect(wrapper.findAll('.datepicker__segment').map((input) => input.element.value)).toEqual([
      '21',
      '03',
      '2015',
    ])
    wrapper.unmount()
  })

  it('turns Arabic digits into Western digits and emits the ISO date once complete', async () => {
    const wrapper = mountPicker()

    await segment(wrapper, 'day').setValue('٢١')
    await segment(wrapper, 'month').setValue('٠3')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    await segment(wrapper, 'year').setValue('٢٠١٥')

    expect(segment(wrapper, 'day').element.value).toBe('21')
    expect(wrapper.emitted('update:modelValue')).toEqual([['2015-03-21']])
    wrapper.unmount()
  })

  it('moves to the next box when one is full and spills extra digits into it', async () => {
    const wrapper = mountPicker()

    await segment(wrapper, 'day').setValue('07122011')

    expect(wrapper.findAll('.datepicker__segment').map((input) => input.element.value)).toEqual([
      '07',
      '12',
      '2011',
    ])
    expect(wrapper.emitted('update:modelValue')).toEqual([['2011-12-07']])
    expect(document.activeElement).toBe(segment(wrapper, 'year').element)
    wrapper.unmount()
  })

  it('fills the boxes from a pasted date', async () => {
    const wrapper = mountPicker()
    const clipboardData = { getData: () => '٢١/٣/٢٠١٥' }

    await wrapper.find('.datepicker').trigger('paste', { clipboardData })

    expect(wrapper.emitted('update:modelValue')).toEqual([['2015-03-21']])
    wrapper.unmount()
  })

  it('takes the date chosen in the calendar', async () => {
    const wrapper = mountPicker()

    await wrapper.find('input[type="date"]').setValue('2026-07-07')

    expect(wrapper.emitted('update:modelValue')).toEqual([['2026-07-07']])
    expect(segment(wrapper, 'year').element.value).toBe('2026')
    wrapper.unmount()
  })

  it('flags an incomplete date once the focus leaves the field', async () => {
    const wrapper = mountPicker()

    await segment(wrapper, 'day').setValue('31')
    await segment(wrapper, 'month').setValue('02')
    await segment(wrapper, 'year').setValue('2026')
    await wrapper.find('.datepicker').trigger('focusout', { relatedTarget: null })

    expect(wrapper.find('.field__error').text()).toBe('أكمل التاريخ باليوم والشهر والسنة.')
    expect(wrapper.emitted('blur')).toHaveLength(1)
    wrapper.unmount()
  })

  it('clears the boxes when the form resets the value', async () => {
    const wrapper = mountPicker({ modelValue: '2015-03-21' })

    await wrapper.setProps({ modelValue: '' })

    expect(segment(wrapper, 'year').element.value).toBe('')
    wrapper.unmount()
  })
})
