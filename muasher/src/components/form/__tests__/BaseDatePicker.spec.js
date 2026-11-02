import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'

describe('BaseDatePicker', () => {
  it('renders a native labelled date input with min/max bounds', () => {
    const wrapper = mount(BaseDatePicker, {
      props: {
        modelValue: '',
        label: 'تاريخ التأسيس',
        name: 'foundingDate',
        min: '2000-01-01',
        max: '2026-12-31',
      },
    })
    const input = wrapper.find('input[type="date"]')
    expect(input.attributes('min')).toBe('2000-01-01')
    expect(input.attributes('max')).toBe('2026-12-31')
    expect(wrapper.find('label').attributes('for')).toBe(input.attributes('id'))
  })

  it('emits update:modelValue with the ISO date string', async () => {
    const wrapper = mount(BaseDatePicker, {
      props: { modelValue: '', label: 'تاريخ', name: 'date' },
    })
    await wrapper.find('input').setValue('2026-07-07')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2026-07-07'])
  })
})
