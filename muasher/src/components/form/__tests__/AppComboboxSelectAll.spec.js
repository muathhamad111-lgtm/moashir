import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AppCombobox from '@/components/form/AppCombobox.vue'
import { createTestGlobals } from '@/test-utils'

const REGIONS = [
  { value: 1, label: 'منطقة الرياض' },
  { value: 2, label: 'منطقة مكة المكرمة', disabled: true, hint: 'لديها طلبات، فلا يمكن إزالتها' },
  { value: 3, label: 'المنطقة الشرقية' },
]

function mountRegions(props = {}, locale = 'ar') {
  return mount(AppCombobox, {
    props: {
      label: 'المناطق المشمولة',
      options: REGIONS,
      modelValue: [2],
      multiple: true,
      selectAll: true,
      ...props,
    },
    attachTo: document.body,
    global: createTestGlobals({ locale }),
  })
}

function toggle(wrapper) {
  return wrapper.find('.dropdown-select-all__toggle')
}

function lastEmitted(wrapper) {
  return wrapper.emitted('update:modelValue').at(-1)[0]
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('AppCombobox select all (R-1)', () => {
  it('selects every option and keeps the locked one when it clears them all', async () => {
    const wrapper = mountRegions()
    expect(toggle(wrapper).text()).toBe('تحديد الكل')
    expect(wrapper.find('.dropdown-select-all__count').text()).toBe('1 من 3')
    await toggle(wrapper).trigger('click')
    expect(lastEmitted(wrapper)).toEqual([1, 2, 3])
    await wrapper.setProps({ modelValue: [1, 2, 3] })
    expect(toggle(wrapper).text()).toBe('إلغاء الكل')
    await toggle(wrapper).trigger('click')
    expect(lastEmitted(wrapper)).toEqual([2])
  })

  it('shows a locked option as a tag without a remove button and with its reason', () => {
    const wrapper = mountRegions({ modelValue: [1, 2] })
    const tags = wrapper.findAll('.dropdown-tag')
    expect(tags).toHaveLength(2)
    expect(tags[0].find('.dropdown-tag__remove').exists()).toBe(true)
    expect(tags[1].classes()).toContain('is-locked')
    expect(tags[1].attributes('title')).toBe('لديها طلبات، فلا يمكن إزالتها')
    expect(tags[1].find('.dropdown-tag__remove').exists()).toBe(false)
  })

  it('never removes a locked option with Backspace', async () => {
    const wrapper = mountRegions()
    await wrapper.find('input[role="combobox"]').trigger('keydown', { key: 'Backspace' })
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('uses the count the field gives, in English too', async () => {
    const wrapper = mountRegions({ countLabel: '1 of 3 regions' }, 'en')
    expect(wrapper.find('.dropdown-select-all__count').text()).toBe('1 of 3 regions')
    expect(toggle(wrapper).text()).toBe('Select all')
  })

  it('offers no select all on a single choice, nor when the field is disabled', () => {
    expect(
      mountRegions({ multiple: false, modelValue: 1 }).find('.dropdown-select-all').exists(),
    ).toBe(false)
    expect(toggle(mountRegions({ disabled: true })).attributes('disabled')).toBeDefined()
  })
})
