import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BrandColorField from '@/components/branding/BrandColorField.vue'
import BrandContrastNotice from '@/components/branding/BrandContrastNotice.vue'
import BrandDerivedColorRow from '@/components/branding/BrandDerivedColorRow.vue'
import { brandContrastPairs, deriveBrandScheme } from '@/utils/brandingContrast'
import { createTestGlobals } from '@/test-utils'

function mountField(props) {
  return mount(BrandColorField, {
    props: { role: 'primary', modelValue: '#98d8d3', fallback: '#98d8d3', ...props },
    global: createTestGlobals(),
  })
}

describe('BrandColorField', () => {
  it('pairs a labelled colour picker with a left-to-right hex field', async () => {
    const wrapper = mountField()
    const picker = wrapper.find('input[type="color"]')
    expect(picker.attributes('aria-label')).toBe('اختيار اللون الرئيسي من لوحة الألوان')
    expect(picker.element.value).toBe('#98d8d3')
    const text = wrapper.find('input[type="text"]')
    expect(text.attributes('dir')).toBe('ltr')
    expect(wrapper.find('label').text()).toContain('اللون الرئيسي')

    await picker.setValue('#d4088c')
    await text.setValue('#4a2a7d')
    expect(wrapper.emitted('update:modelValue')).toEqual([['#d4088c'], ['#4a2a7d']])
  })

  it('shows an error and keeps the picker on the fallback for an invalid code', () => {
    const wrapper = mountField({ modelValue: '#12' })
    expect(wrapper.text()).toContain('أدخل اللون برمز من ست خانات')
    expect(wrapper.find('input[type="color"]').element.value).toBe('#98d8d3')
  })
})

describe('BrandContrastNotice', () => {
  function mountNotice(colors) {
    const scheme = deriveBrandScheme(colors)
    return mount(BrandContrastNotice, {
      props: { pairs: brandContrastPairs(scheme) },
      global: createTestGlobals(),
    })
  }

  it('stays silent when every pair passes, the darker text shade included', () => {
    expect(
      mountNotice({ primary: '#d4088c', secondary: '#4a2a7d' }).find('.brand-contrast').exists(),
    ).toBe(false)
    expect(
      mountNotice({ primary: '#98d8d3', secondary: '#0a2325' }).find('.brand-contrast').exists(),
    ).toBe(false)
  })

  it('warns that saving is blocked and lists only the failing pairs with their ratio', () => {
    const wrapper = mountNotice({ primary: '#777777', secondary: '#0a2325' })
    const alert = wrapper.find('.brand-contrast--blocked')
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.text()).toContain('لا يمكن الحفظ')
    expect(alert.findAll('.brand-contrast__row')).toHaveLength(1)
    expect(alert.text()).toContain('النص فوق اللون الرئيسي')
  })
})

describe('BrandDerivedColorRow', () => {
  function mountRow(props) {
    return mount(BrandDerivedColorRow, {
      props: {
        colorKey: 'onSecondary',
        value: '#ffffff',
        check: { ratio: 16.4, passes: true },
        ...props,
      },
      global: createTestGlobals({ locale: 'en' }),
      attachTo: document.createElement('tbody'),
    })
  }

  it('shows an auto colour with its use, value and passing contrast, and offers an override', async () => {
    const wrapper = mountRow()
    expect(wrapper.find('th[scope="row"]').text()).toContain('Text on secondary')
    expect(wrapper.text()).toContain('Top-bar text and icons')
    expect(wrapper.find('.brand-derived__hex').text()).toBe('#ffffff')
    expect(wrapper.find('.brand-derived__ratio--pass').text()).toContain('16.4:1')
    expect(wrapper.text()).toContain('Meets the required contrast')
    await wrapper.find('.btn').trigger('click')
    expect(wrapper.emitted('override')).toEqual([['#ffffff']])
  })

  it('shows a custom colour with its auto value, a labelled picker and a way back to auto', async () => {
    const wrapper = mountRow({
      value: '#6b8a8c',
      autoValue: '#ffffff',
      isCustom: true,
      check: { ratio: 4.4, passes: false },
    })
    expect(wrapper.classes()).toContain('brand-derived__row--failing')
    expect(wrapper.find('.brand-derived__auto').text()).toContain('#ffffff')
    expect(wrapper.find('.brand-derived__picker').attributes('aria-label')).toBe(
      'Pick the “Text on secondary” color',
    )
    expect(wrapper.text()).toContain('Misses the required contrast')
    await wrapper.find('.brand-derived__picker').setValue('#123456')
    await wrapper.find('.btn').trigger('click')
    expect(wrapper.emitted('override')).toEqual([['#123456']])
    expect(wrapper.emitted('restore-auto')).toHaveLength(1)
  })
})
