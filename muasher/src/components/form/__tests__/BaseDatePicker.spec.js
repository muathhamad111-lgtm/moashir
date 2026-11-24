import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

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

function calendarButton(wrapper) {
  return wrapper.find('.datepicker__calendar')
}

async function openCalendar(wrapper) {
  await calendarButton(wrapper).trigger('click')
  await flushPromises()
}

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

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

  it('puts the field name on the day box and has no hidden browser date input', () => {
    const wrapper = mountPicker({ modelValue: '2015-03-21' })

    expect(segment(wrapper, 'day').attributes('name')).toBe('foundedOn')
    expect(segment(wrapper, 'month').attributes('name')).toBeUndefined()
    expect(segment(wrapper, 'year').attributes('name')).toBeUndefined()
    expect(wrapper.find('input[type="date"]').exists()).toBe(false)
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

  it('fills the three boxes from a whole date typed into one of them', async () => {
    const wrapper = mountPicker()

    await segment(wrapper, 'day').setValue('2015-03-21')

    expect(wrapper.findAll('.datepicker__segment').map((input) => input.element.value)).toEqual([
      '21',
      '03',
      '2015',
    ])
    expect(wrapper.emitted('update:modelValue')).toEqual([['2015-03-21']])
    wrapper.unmount()
  })

  it('fills the boxes from a pasted date', async () => {
    const wrapper = mountPicker()
    const clipboardData = { getData: () => '٢١/٣/٢٠١٥' }

    await wrapper.find('.datepicker').trigger('paste', { clipboardData })

    expect(wrapper.emitted('update:modelValue')).toEqual([['2015-03-21']])
    wrapper.unmount()
  })

  it('opens the in-app calendar from the button with the bounds applied', async () => {
    const wrapper = mountPicker({ modelValue: '2026-07-15', min: '2026-07-05', max: '2026-07-20' })

    expect(calendarButton(wrapper).attributes('aria-haspopup')).toBe('dialog')
    expect(calendarButton(wrapper).attributes('aria-expanded')).toBe('false')
    await openCalendar(wrapper)
    const dialog = wrapper.find('[role="dialog"]')

    expect(dialog.attributes('aria-label')).toBe('تقويم تاريخ التأسيس')
    expect(calendarButton(wrapper).attributes('aria-expanded')).toBe('true')
    expect(calendarButton(wrapper).attributes('aria-controls')).toBe(dialog.attributes('id'))
    expect(wrapper.find('.datepicker').classes()).toContain('is-open')
    expect(wrapper.find('.date-calendar__title').text()).toBe('يوليو 2026')
    expect(wrapper.find('[data-date="2026-07-04"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-date="2026-07-21"]').attributes('disabled')).toBeDefined()
    expect(document.activeElement).toBe(wrapper.find('[data-date="2026-07-15"]').element)
    wrapper.unmount()
  })

  it('takes the day chosen in the calendar, closes it and returns the focus to the button', async () => {
    const wrapper = mountPicker({ modelValue: '2026-07-15' })

    await openCalendar(wrapper)
    await wrapper.find('[data-date="2026-07-07"]').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toEqual([['2026-07-07']])
    expect(wrapper.findAll('.datepicker__segment').map((input) => input.element.value)).toEqual([
      '07',
      '07',
      '2026',
    ])
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(calendarButton(wrapper).element)
    expect(wrapper.emitted('blur')).toBeUndefined()
    wrapper.unmount()
  })

  it('closes the calendar on Escape, keeps a parent dialog open and returns the focus', async () => {
    const outer = vi.fn()
    document.body.addEventListener('keydown', outer)
    const wrapper = mountPicker({ modelValue: '2026-07-15' })

    await openCalendar(wrapper)
    await wrapper.find('[data-date="2026-07-15"]').trigger('keydown', { key: 'Escape' })
    await flushPromises()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(calendarButton(wrapper).element)
    expect(outer).not.toHaveBeenCalled()
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    document.body.removeEventListener('keydown', outer)
    wrapper.unmount()
  })

  it('closes the calendar on a press outside and toggles it from the button', async () => {
    const wrapper = mountPicker({ modelValue: '2026-07-15' })

    await openCalendar(wrapper)
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

    await openCalendar(wrapper)
    await openCalendar(wrapper)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('opens the calendar as a bottom sheet on a phone', async () => {
    stubMatchMedia({ width: 390 })
    const wrapper = mountPicker({ modelValue: '2026-07-15' })

    await openCalendar(wrapper)
    const sheet = wrapper.find('.dropdown-sheet')

    expect(sheet.exists()).toBe(true)
    expect(sheet.attributes('role')).toBe('dialog')
    expect(sheet.find('.dropdown-sheet__title').text()).toBe('تاريخ التأسيس')
    expect(sheet.find('.date-calendar').attributes('role')).toBeUndefined()
    expect(wrapper.find('.dropdown-popup').exists()).toBe(false)
    await sheet.find('.dropdown-sheet__close').trigger('click')
    await flushPromises()
    expect(wrapper.find('.dropdown-sheet').exists()).toBe(false)
    expect(document.activeElement).toBe(calendarButton(wrapper).element)
    wrapper.unmount()
  })

  it('does not open the calendar while disabled', async () => {
    const wrapper = mountPicker({ disabled: true })

    await openCalendar(wrapper)

    expect(calendarButton(wrapper).attributes('disabled')).toBeDefined()
    expect(wrapper.find('.date-calendar').exists()).toBe(false)
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
