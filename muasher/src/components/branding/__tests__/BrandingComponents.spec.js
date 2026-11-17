import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BrandColorField from '@/components/branding/BrandColorField.vue'
import BrandContrastNotice from '@/components/branding/BrandContrastNotice.vue'
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
      props: { pairs: brandContrastPairs(scheme), scheme },
      global: createTestGlobals(),
    })
  }

  it('stays silent when every pair passes and the primary is its own text colour', () => {
    const wrapper = mountNotice({ primary: '#d4088c', secondary: '#4a2a7d' })
    expect(wrapper.find('.brand-contrast').exists()).toBe(false)
  })

  it('notes the darker text shade used for a light primary colour', () => {
    const wrapper = mountNotice({ primary: '#98d8d3', secondary: '#0a2325' })
    const note = wrapper.find('.brand-contrast--note')
    expect(note.attributes('role')).toBe('status')
    expect(note.find('bdi').text()).toMatch(/^#[0-9a-f]{6}$/)
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
