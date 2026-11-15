import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AccentSwatchPicker from '@/components/branding/AccentSwatchPicker.vue'
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
