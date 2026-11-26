import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BrandColorField from '@/components/branding/BrandColorField.vue'
import BrandDerivedColorRow from '@/components/branding/BrandDerivedColorRow.vue'
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

describe('BrandDerivedColorRow', () => {
  function mountRow(props) {
    return mount(BrandDerivedColorRow, {
      props: {
        colorKey: 'onSecondary',
        value: '#ffffff',
        ...props,
      },
      global: createTestGlobals({ locale: 'en' }),
      attachTo: document.createElement('ul'),
    })
  }

  it('shows an auto colour with its use and value, no contrast verdict, and offers an override', async () => {
    const wrapper = mountRow()
    expect(wrapper.element.tagName).toBe('LI')
    expect(wrapper.find('.brand-derived__name').text()).toContain('Text on secondary')
    expect(wrapper.text()).toContain('Top-bar text and icons')
    expect(wrapper.find('.brand-derived__hex').text()).toBe('#ffffff')
    expect(wrapper.text()).toContain('Auto')
    expect(wrapper.text()).not.toMatch(/contrast|:1/i)
    await wrapper.find('.btn').trigger('click')
    expect(wrapper.emitted('override')).toEqual([['#ffffff']])
  })

  it('shows a custom colour with its auto value, a labelled picker and a way back to auto', async () => {
    const wrapper = mountRow({
      value: '#6b8a8c',
      autoValue: '#ffffff',
      isCustom: true,
    })
    expect(wrapper.text()).toContain('Custom')
    expect(wrapper.find('.brand-derived__auto').text()).toContain('#ffffff')
    expect(wrapper.find('.brand-derived__picker').attributes('aria-label')).toBe(
      'Pick the “Text on secondary” color',
    )
    await wrapper.find('.brand-derived__picker').setValue('#123456')
    await wrapper.find('.btn').trigger('click')
    expect(wrapper.emitted('override')).toEqual([['#123456']])
    expect(wrapper.emitted('restore-auto')).toHaveLength(1)
  })
})
