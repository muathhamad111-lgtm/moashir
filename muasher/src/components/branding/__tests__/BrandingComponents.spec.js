import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AccentSwatchPicker from '@/components/branding/AccentSwatchPicker.vue'
import ContrastCheck from '@/components/branding/ContrastCheck.vue'
import { createTestGlobals } from '@/test-utils'

const palette = [
  { token: 'brand_default', label: { ar: 'الافتراضي', en: 'Brand default' } },
  { token: 'teal', label: { ar: 'فيروزي', en: 'Teal' } },
]

describe('AccentSwatchPicker', () => {
  it('is a labelled radio group that emits the chosen token', async () => {
    const wrapper = mount(AccentSwatchPicker, {
      props: { palette, modelValue: 'brand_default', localized: (value) => value.en },
      global: createTestGlobals(),
    })
    expect(wrapper.find('fieldset legend').text()).toBe('لوحة الألوان المعتمدة')
    const radios = wrapper.findAll('input[type="radio"]')
    expect(radios).toHaveLength(2)
    expect(radios[0].element.checked).toBe(true)
    await radios[1].setValue(true)
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['teal'])
    expect(wrapper.findAll('.accent-swatch__chip')[1].attributes('data-accent')).toBe('teal')
  })
})

describe('ContrastCheck', () => {
  it('shows each ratio with a text badge, never color alone', () => {
    const wrapper = mount(ContrastCheck, {
      props: {
        pairs: [
          { key: 'fill', ratio: 10.49, passes: true },
          { key: 'light', ratio: 3.1, passes: false },
        ],
      },
      global: createTestGlobals({ locale: 'en' }),
    })
    const rows = wrapper.findAll('.contrast-check__row')
    expect(rows[0].text()).toContain('10.49:1')
    expect(rows[0].text()).toContain('Passes AA')
    expect(rows[1].text()).toContain('Fails AA')
  })
})
