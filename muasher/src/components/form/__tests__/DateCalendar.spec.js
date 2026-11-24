import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import DateCalendar from '@/components/form/DateCalendar.vue'
import { createTestGlobals } from '@/test-utils'

const NOW = new Date('2026-11-23T09:00:00Z')

async function mountCalendar(props = {}, locale = 'ar') {
  const wrapper = mount(DateCalendar, {
    props: { label: 'تاريخ التأسيس', ...props },
    attachTo: document.body,
    global: createTestGlobals({ locale }),
  })
  await flushPromises()
  return wrapper
}

function day(wrapper, iso) {
  return wrapper.find(`[data-date="${iso}"]`)
}

function focusedDate() {
  return document.activeElement?.dataset?.date
}

async function press(wrapper, key, options = {}) {
  await wrapper.find('.date-calendar__day[tabindex="0"]').trigger('keydown', { key, ...options })
  await flushPromises()
}

function title(wrapper) {
  return wrapper.find('.date-calendar__title').text()
}

beforeEach(() => {
  vi.useFakeTimers({ now: NOW, toFake: ['Date'] })
})

afterEach(() => {
  vi.useRealTimers()
  document.body.innerHTML = ''
})

describe('DateCalendar', () => {
  it('shows a labelled dialog with a Sunday-first Arabic month and today marked', async () => {
    const wrapper = await mountCalendar()
    const dialog = wrapper.find('[role="dialog"]')
    const grid = wrapper.find('[role="grid"]')
    const weekdays = wrapper.findAll('th')

    expect(dialog.attributes('aria-label')).toBe('تقويم تاريخ التأسيس')
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(title(wrapper)).toBe('نوفمبر 2026')
    expect(wrapper.find(`#${grid.attributes('aria-labelledby')}`).text()).toBe('نوفمبر 2026')
    expect(weekdays.map((weekday) => weekday.text())[0]).toBe('الأحد')
    expect(weekdays.map((weekday) => weekday.text())[6]).toBe('السبت')
    expect(wrapper.findAll('[role="gridcell"]')).toHaveLength(42)
    expect(wrapper.find('[aria-current="date"]').attributes('data-date')).toBe('2026-11-23')
    expect(day(wrapper, '2026-11-23').classes()).toContain('is-today')
    expect(day(wrapper, '2026-12-01').classes()).toContain('is-outside-month')
    expect(focusedDate()).toBe('2026-11-23')
    wrapper.unmount()
  })

  it('names the month, weekdays and days in English', async () => {
    const wrapper = await mountCalendar({ label: 'Founding date' }, 'en')

    expect(wrapper.find('[role="dialog"]').attributes('aria-label')).toBe('Founding date calendar')
    expect(title(wrapper)).toBe('November 2026')
    expect(wrapper.find('th').text()).toBe('Sun')
    expect(wrapper.find('th').attributes('abbr')).toBe('Sunday')
    expect(day(wrapper, '2026-11-23').attributes('aria-label')).toBe('Monday, November 23, 2026')
    expect(day(wrapper, '2026-11-23').text()).toBe('23')
    wrapper.unmount()
  })

  it('opens on the selected date and emits the chosen day as YYYY-MM-DD', async () => {
    const wrapper = await mountCalendar({ selected: '2015-03-21' })

    expect(title(wrapper)).toBe('مارس 2015')
    expect(day(wrapper, '2015-03-21').element.parentElement.getAttribute('aria-selected')).toBe(
      'true',
    )
    expect(focusedDate()).toBe('2015-03-21')
    await day(wrapper, '2015-03-05').trigger('click')

    expect(wrapper.emitted('select')).toEqual([['2015-03-05']])
    wrapper.unmount()
  })

  it('disables the days outside min and max and the months beyond them', async () => {
    const wrapper = await mountCalendar({ min: '2026-11-10', max: '2026-11-25' })

    expect(day(wrapper, '2026-11-09').attributes('disabled')).toBeDefined()
    expect(day(wrapper, '2026-11-09').element.parentElement.getAttribute('aria-disabled')).toBe(
      'true',
    )
    expect(day(wrapper, '2026-11-26').attributes('disabled')).toBeDefined()
    expect(day(wrapper, '2026-11-10').attributes('disabled')).toBeUndefined()
    expect(day(wrapper, '2026-11-10').element.parentElement.hasAttribute('aria-disabled')).toBe(
      false,
    )
    expect(wrapper.find('.date-calendar__nav--previous').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.date-calendar__nav--next').attributes('disabled')).toBeDefined()

    await press(wrapper, 'End')
    await press(wrapper, 'ArrowDown')
    expect(focusedDate()).toBe('2026-11-25')
    await press(wrapper, 'Enter')
    expect(wrapper.emitted('select')).toEqual([['2026-11-25']])
    wrapper.unmount()
  })

  it('moves by day, week, month and to the week edges from the keyboard (LTR)', async () => {
    const wrapper = await mountCalendar({ label: 'Founding date' }, 'en')

    await press(wrapper, 'ArrowRight')
    expect(focusedDate()).toBe('2026-11-24')
    await press(wrapper, 'ArrowLeft')
    await press(wrapper, 'ArrowLeft')
    expect(focusedDate()).toBe('2026-11-22')
    await press(wrapper, 'ArrowDown')
    expect(focusedDate()).toBe('2026-11-29')
    await press(wrapper, 'ArrowUp')
    expect(focusedDate()).toBe('2026-11-22')
    await press(wrapper, 'End')
    expect(focusedDate()).toBe('2026-11-28')
    await press(wrapper, 'Home')
    expect(focusedDate()).toBe('2026-11-22')
    await press(wrapper, 'PageDown')
    expect(focusedDate()).toBe('2026-12-22')
    expect(title(wrapper)).toBe('December 2026')
    await press(wrapper, 'PageUp', { shiftKey: true })
    expect(focusedDate()).toBe('2025-12-22')
    await press(wrapper, ' ')
    expect(wrapper.emitted('select')).toEqual([['2025-12-22']])
    wrapper.unmount()
  })

  it('mirrors the arrows and the month chevrons in RTL', async () => {
    const wrapper = await mountCalendar()
    const buttons = wrapper.findAll('.date-calendar__header button')

    expect(buttons[0].classes()).toContain('date-calendar__nav--previous')
    expect(buttons[0].attributes('aria-label')).toBe('الشهر السابق')
    expect(buttons[0].find('[data-icon="chevron-left"]').classes()).toContain('app-icon--mirror')
    expect(buttons[1].classes()).toContain('date-calendar__nav--next')
    expect(buttons[1].attributes('aria-label')).toBe('الشهر التالي')
    expect(buttons[1].find('[data-icon="chevron-right"]').classes()).toContain('app-icon--mirror')

    await press(wrapper, 'ArrowLeft')
    expect(focusedDate()).toBe('2026-11-24')
    await press(wrapper, 'ArrowRight')
    expect(focusedDate()).toBe('2026-11-23')

    await buttons[1].trigger('click')
    await flushPromises()
    expect(title(wrapper)).toBe('ديسمبر 2026')
    await buttons[0].trigger('click')
    await buttons[0].trigger('click')
    await flushPromises()
    expect(title(wrapper)).toBe('أكتوبر 2026')
    wrapper.unmount()
  })

  it('closes on Escape without reaching a parent dialog', async () => {
    const outer = vi.fn()
    document.body.addEventListener('keydown', outer)
    const wrapper = await mountCalendar()

    await press(wrapper, 'Escape')

    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(outer).not.toHaveBeenCalled()
    document.body.removeEventListener('keydown', outer)
    wrapper.unmount()
  })

  it('keeps Tab inside the popover and leaves it to the sheet when shown as one', async () => {
    const wrapper = await mountCalendar()
    const previous = wrapper.find('.date-calendar__nav--previous')
    previous.element.focus()

    await previous.trigger('keydown', { key: 'Tab', shiftKey: true })
    expect(focusedDate()).toBe('2026-11-23')
    await press(wrapper, 'Tab')
    expect(document.activeElement).toBe(previous.element)

    await wrapper.setProps({ isSheet: true })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
