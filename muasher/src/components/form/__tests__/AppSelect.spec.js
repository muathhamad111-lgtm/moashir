import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import AppSelect from '@/components/form/AppSelect.vue'
import BaseModal from '@/components/modal/BaseModal.vue'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import { TYPEAHEAD_RESET_MS } from '@/constants/dropdown'

const REGIONS = [
  { value: '1', label: 'منطقة الرياض' },
  { value: '2', label: 'منطقة مكة المكرمة' },
  { value: '3', label: 'المنطقة الشرقية' },
  { value: '4', label: 'منطقة القصيم', disabled: true },
  { value: '5', label: 'منطقة المدينة المنورة' },
  { value: '6', label: 'منطقة عسير' },
  { value: '7', label: 'منطقة تبوك' },
  { value: '8', label: 'منطقة حائل' },
  { value: '9', label: 'منطقة الحدود الشمالية' },
  { value: '10', label: 'منطقة جازان' },
  { value: '11', label: 'منطقة نجران' },
  { value: '12', label: 'منطقة الباحة' },
]

const STATUSES = [
  { value: '', label: 'All statuses' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'closed', label: 'Closed' },
]

function mountSelect(props = {}, { locale = 'ar' } = {}) {
  return mount(AppSelect, {
    props: { label: 'المنطقة', name: 'regionId', options: REGIONS, modelValue: null, ...props },
    attachTo: document.body,
    global: createTestGlobals({ locale }),
  })
}

function trigger(wrapper) {
  return wrapper.find('button[role="combobox"]')
}

async function press(wrapper, key, init = {}) {
  await trigger(wrapper).trigger('keydown', { key, ...init })
}

function activeLabel(wrapper) {
  const id = trigger(wrapper).attributes('aria-activedescendant')
  return id ? wrapper.find(`#${id}`).find('.dropdown-option__label').text() : null
}

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('AppSelect (field)', () => {
  it('renders a labelled select-only combobox that controls a listbox only while open', async () => {
    const wrapper = mountSelect({ required: true, hint: 'اختر المنطقة الإدارية', modelValue: '3' })
    const button = trigger(wrapper)
    expect(button.attributes('aria-haspopup')).toBe('listbox')
    expect(button.attributes('aria-expanded')).toBe('false')
    expect(button.attributes('aria-controls')).toBeUndefined()
    expect(button.attributes('aria-required')).toBe('true')
    expect(wrapper.find(`label[for="${button.attributes('id')}"]`).text()).toBe('المنطقة')
    expect(button.attributes('aria-describedby')).toBe(
      wrapper.find('.field__hint').attributes('id'),
    )
    expect(button.text()).toContain('المنطقة الشرقية')
    await button.trigger('click')
    const listbox = wrapper.find('[role="listbox"]')
    expect(button.attributes('aria-expanded')).toBe('true')
    expect(button.attributes('aria-controls')).toBe(listbox.attributes('id'))
    expect(listbox.attributes('aria-label')).toBe('المنطقة')
    const options = wrapper.findAll('[role="option"]')
    expect(options).toHaveLength(REGIONS.length)
    expect(options[2].attributes('aria-selected')).toBe('true')
    expect(options[0].attributes('aria-selected')).toBe('false')
    expect(options[3].attributes('aria-disabled')).toBe('true')
    expect(new Set(options.map((option) => option.attributes('id'))).size).toBe(REGIONS.length)
  })

  it('opens on the selected option and shows the active option to screen readers and sight', async () => {
    const wrapper = mountSelect({ modelValue: '2' })
    await press(wrapper, 'Enter')
    expect(activeLabel(wrapper)).toBe('منطقة مكة المكرمة')
    expect(wrapper.find('.dropdown-option.is-active').text()).toContain('منطقة مكة المكرمة')
  })

  it('opens with Space, ArrowDown and ArrowUp and on the first or last option with Home and End', async () => {
    for (const key of [' ', 'ArrowDown', 'ArrowUp']) {
      const wrapper = mountSelect()
      await press(wrapper, key)
      expect(trigger(wrapper).attributes('aria-expanded')).toBe('true')
      expect(activeLabel(wrapper)).toBe('منطقة الرياض')
      wrapper.unmount()
    }
    const home = mountSelect({ modelValue: '5' })
    await press(home, 'Home')
    expect(activeLabel(home)).toBe('منطقة الرياض')
    const end = mountSelect()
    await press(end, 'End')
    expect(activeLabel(end)).toBe('منطقة الباحة')
  })

  it('moves without wrapping, jumps with Home, End, PageUp and PageDown', async () => {
    const wrapper = mountSelect()
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'ArrowUp')
    expect(activeLabel(wrapper)).toBe('منطقة الرياض')
    await press(wrapper, 'ArrowDown')
    expect(activeLabel(wrapper)).toBe('منطقة مكة المكرمة')
    await press(wrapper, 'PageDown')
    expect(activeLabel(wrapper)).toBe('منطقة الباحة')
    await press(wrapper, 'ArrowDown')
    expect(activeLabel(wrapper)).toBe('منطقة الباحة')
    await press(wrapper, 'PageUp')
    expect(activeLabel(wrapper)).toBe('منطقة مكة المكرمة')
    await press(wrapper, 'End')
    expect(activeLabel(wrapper)).toBe('منطقة الباحة')
    await press(wrapper, 'Home')
    expect(activeLabel(wrapper)).toBe('منطقة الرياض')
  })

  it('selects with Enter and with Alt+ArrowUp, then closes on the trigger', async () => {
    const wrapper = mountSelect()
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'Enter')
    expect(wrapper.emitted('update:modelValue')).toEqual([['2']])
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'ArrowUp', { altKey: true })
    expect(wrapper.emitted('update:modelValue')[1]).toEqual(['2'])
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
  })

  it('selects with Space when no type-ahead is running and ignores disabled options', async () => {
    const wrapper = mountSelect()
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'End')
    await press(wrapper, ' ')
    expect(wrapper.emitted('update:modelValue')).toEqual([['12']])
    await trigger(wrapper).trigger('click')
    await wrapper.findAll('[role="option"]')[3].trigger('click')
    expect(wrapper.emitted('update:modelValue')).toHaveLength(1)
    expect(wrapper.find('[role="listbox"]').exists()).toBe(true)
  })

  it('jumps by typed Arabic characters with normalisation and resets after the timeout', async () => {
    vi.useFakeTimers()
    const wrapper = mountSelect()
    await press(wrapper, 'ا')
    expect(trigger(wrapper).attributes('aria-expanded')).toBe('true')
    expect(activeLabel(wrapper)).toBe('المنطقة الشرقية')
    vi.advanceTimersByTime(TYPEAHEAD_RESET_MS)
    await press(wrapper, 'م')
    await press(wrapper, 'ن')
    await press(wrapper, 'ط')
    await press(wrapper, 'ق')
    await press(wrapper, 'ه')
    await press(wrapper, ' ')
    await press(wrapper, 'ع')
    expect(activeLabel(wrapper)).toBe('منطقة عسير')
    vi.advanceTimersByTime(TYPEAHEAD_RESET_MS)
    await press(wrapper, 'م')
    expect(activeLabel(wrapper)).toBe('منطقة تبوك')
    await press(wrapper, 'م')
    expect(activeLabel(wrapper)).toBe('منطقة حائل')
  })

  it('commits the active option on Tab in a form field (PM decision 3) without trapping focus', async () => {
    const wrapper = mountSelect()
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'ArrowDown')
    const event = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true })
    trigger(wrapper).element.dispatchEvent(event)
    await nextTick()
    expect(event.defaultPrevented).toBe(false)
    expect(wrapper.emitted('update:modelValue')).toEqual([['2']])
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
  })

  it('closes on Escape without a change and keeps the focus on the trigger', async () => {
    const wrapper = mountSelect()
    trigger(wrapper).element.focus()
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'Escape')
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(document.activeElement).toBe(trigger(wrapper).element)
  })

  it('keeps the surrounding dialog open when Escape closes the list', async () => {
    const choice = ref(null)
    const isOpen = ref(true)
    const Host = defineComponent({
      setup() {
        return () =>
          h(
            BaseModal,
            {
              modelValue: isOpen.value,
              title: 'إضافة مدينة',
              'onUpdate:modelValue': (value) => (isOpen.value = value),
            },
            () =>
              h(AppSelect, {
                label: 'المنطقة',
                name: 'regionId',
                options: REGIONS,
                modelValue: choice.value,
              }),
          )
      },
    })
    mount(Host, { attachTo: document.body, global: createTestGlobals() })
    await flushPromises()
    const button = document.querySelector('button[role="combobox"]')
    button.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
    await nextTick()
    expect(document.querySelector('[role="listbox"]')).not.toBeNull()
    button.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(document.querySelector('[role="listbox"]')).toBeNull()
    expect(isOpen.value).toBe(true)
    button.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await nextTick()
    expect(isOpen.value).toBe(false)
  })

  it('chooses with the pointer and moves the active option on hover', async () => {
    const wrapper = mountSelect()
    await trigger(wrapper).trigger('click')
    const options = wrapper.findAll('[role="option"]')
    await options[4].trigger('mousemove')
    expect(activeLabel(wrapper)).toBe('منطقة المدينة المنورة')
    await options[4].trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([['5']])
  })

  it('closes on a pointer press outside and emits blur only once closed', async () => {
    const wrapper = mountSelect()
    await trigger(wrapper).trigger('click')
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    await nextTick()
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
    await trigger(wrapper).trigger('blur')
    expect(wrapper.emitted('blur')).toHaveLength(1)
  })

  it('shows the error state with aria-invalid and the placeholder when empty', async () => {
    const wrapper = mountSelect({ error: 'هذا الحقل مطلوب', placeholder: 'اختر قيمة' })
    const button = trigger(wrapper)
    expect(button.attributes('aria-invalid')).toBe('true')
    expect(button.attributes('aria-describedby')).toBe(
      wrapper.find('.field__error').attributes('id'),
    )
    expect(wrapper.find('.dropdown-field__value.is-placeholder').text()).toBe('اختر قيمة')
  })

  it('does not open when disabled', async () => {
    const wrapper = mountSelect({ disabled: true })
    await trigger(wrapper).trigger('click')
    await press(wrapper, 'ArrowDown')
    expect(trigger(wrapper).attributes('disabled')).toBeDefined()
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
  })

  it('announces loading and empty lists outside the listbox', async () => {
    const loading = mountSelect({ loading: true })
    await trigger(loading).trigger('click')
    expect(loading.find('[role="status"]').text()).toBe('جارٍ تحميل الخيارات…')
    const empty = mountSelect({ options: [] }, { locale: 'en' })
    await trigger(empty).trigger('click')
    expect(empty.find('[role="status"]').text()).toBe('No options available yet')
  })

  it('keeps a visually hidden label for a compact table cell', () => {
    const wrapper = mountSelect({ hideLabel: true })
    expect(wrapper.find('.field').classes()).toContain('field--label-hidden')
    expect(wrapper.find('.field__label').text()).toBe('المنطقة')
  })
})

describe('AppSelect (filter)', () => {
  function mountFilter(props = {}) {
    return mount(AppSelect, {
      props: { variant: 'filter', label: 'Status', options: STATUSES, modelValue: '', ...props },
      attachTo: document.body,
      global: createTestGlobals({ locale: 'en' }),
    })
  }

  it('is named by its visible label prefix, not by its value', () => {
    const wrapper = mountFilter()
    const button = trigger(wrapper)
    const label = wrapper.find(`#${button.attributes('aria-labelledby')}`)
    expect(label.text()).toBe('Status')
    expect(wrapper.find('.dropdown-filter__label').text()).toBe('Status:')
    expect(wrapper.find('.dropdown-filter__value').text()).toBe('All statuses')
    expect(wrapper.find('.dropdown-filter').classes()).not.toContain('is-applied')
  })

  it('marks a non-default value as applied', () => {
    expect(mountFilter({ modelValue: 'closed' }).find('.dropdown-filter').classes()).toContain(
      'is-applied',
    )
  })

  it('closes on Tab without changing the filter (PM decision 3)', async () => {
    const wrapper = mountFilter()
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'ArrowDown')
    await press(wrapper, 'Tab')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.find('[role="listbox"]').exists()).toBe(false)
  })

  it('does not re-emit the value that is already chosen', async () => {
    const wrapper = mountFilter({ modelValue: 'submitted' })
    await press(wrapper, 'Enter')
    await press(wrapper, 'Enter')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })
})

describe('AppSelect below 768 px', () => {
  it('opens a bottom sheet titled by the field label and moves the focus to the list', async () => {
    stubMatchMedia({ width: 390 })
    const wrapper = mountSelect({ modelValue: '1' })
    const button = trigger(wrapper)
    expect(button.attributes('aria-haspopup')).toBe('dialog')
    await button.trigger('click')
    await flushPromises()
    const sheet = wrapper.find('.dropdown-sheet')
    expect(sheet.attributes('role')).toBe('dialog')
    expect(sheet.attributes('aria-modal')).toBe('true')
    expect(wrapper.find(`#${sheet.attributes('aria-labelledby')}`).text()).toBe('المنطقة')
    const listbox = wrapper.find('[role="listbox"]')
    expect(document.activeElement).toBe(listbox.element)
    expect(listbox.attributes('aria-activedescendant')).toBe(
      wrapper.find('.is-active').attributes('id'),
    )
    await listbox.trigger('keydown', { key: 'ArrowDown' })
    await listbox.trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(wrapper.emitted('update:modelValue')).toEqual([['2']])
    expect(wrapper.find('.dropdown-sheet').exists()).toBe(false)
    expect(document.activeElement).toBe(button.element)
  })

  it('closes from the close button, the scrim and Escape and returns the focus', async () => {
    stubMatchMedia({ width: 390 })
    const wrapper = mountSelect()
    for (const close of [
      () => wrapper.find('.dropdown-sheet__close').trigger('click'),
      () => wrapper.find('.dropdown-sheet').trigger('click'),
      () => wrapper.find('.dropdown-sheet').trigger('keydown', { key: 'Escape' }),
    ]) {
      await trigger(wrapper).trigger('click')
      await flushPromises()
      expect(wrapper.find('.dropdown-sheet').exists()).toBe(true)
      await close()
      await flushPromises()
      expect(wrapper.find('.dropdown-sheet').exists()).toBe(false)
      expect(document.activeElement).toBe(trigger(wrapper).element)
    }
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('keeps Tab inside the sheet', async () => {
    stubMatchMedia({ width: 390 })
    const wrapper = mountSelect()
    await trigger(wrapper).trigger('click')
    await flushPromises()
    const close = wrapper.find('.dropdown-sheet__close')
    const listbox = wrapper.find('[role="listbox"]')
    listbox.element.focus()
    await wrapper.find('.dropdown-sheet').trigger('keydown', { key: 'Tab' })
    expect(document.activeElement).toBe(close.element)
    await wrapper.find('.dropdown-sheet').trigger('keydown', { key: 'Tab', shiftKey: true })
    expect(document.activeElement).toBe(listbox.element)
  })

  it('switches between the popup and the sheet when the viewport crosses the breakpoint', async () => {
    const media = stubMatchMedia({ width: 1440 })
    const wrapper = mountSelect()
    await trigger(wrapper).trigger('click')
    expect(wrapper.find('.dropdown-popup').exists()).toBe(true)
    media.resize(390)
    await flushPromises()
    expect(wrapper.find('.dropdown-popup').exists()).toBe(false)
    expect(wrapper.find('.dropdown-sheet').exists()).toBe(true)
  })
})
