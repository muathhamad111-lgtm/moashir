import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import RawScoreField from '@/components/assessment/RawScoreField.vue'
import { createTestGlobals } from '@/test-utils'

function mountField({ value = '', saved = null, disabled = false } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  const Host = defineComponent({
    setup() {
      useForm({ initialValues: { 'point-1001': value } })
      return () =>
        h(RawScoreField, {
          name: 'point-1001',
          label: 'Score of M1.1.1',
          maximum: '10.00',
          saved,
          disabled,
        })
    },
  })
  return mount(Host, { global: { plugins: globals.plugins } })
}

describe('RawScoreField', () => {
  it('shows the saved raw value and the percent exactly as returned by the server', async () => {
    const wrapper = mountField({ value: '7.50', saved: { rawValue: '7.50', percent: '75.00' } })
    await flushPromises()
    expect(wrapper.find('label').text()).toBe('Score of M1.1.1')
    expect(wrapper.find('.field__hint').text()).toBe('Saved 7.50 of 10.00, reflected as 75.00%')
    expect(wrapper.find('.raw-score--saved').exists()).toBe(true)
  })

  it('never computes a percent for an unsaved value', async () => {
    const wrapper = mountField({ value: '7.50', saved: { rawValue: '7.50', percent: '75.00' } })
    await wrapper.find('input').setValue('9')
    await flushPromises()
    expect(wrapper.find('.field__hint').text()).toBe('Not saved yet, out of 10.00')
    expect(wrapper.text()).not.toContain('90')
  })

  it('treats 7.5 and 7.50 as the same saved value and guides an empty field', async () => {
    const same = mountField({ value: '7.5', saved: { rawValue: '7.50', percent: '75.00' } })
    await flushPromises()
    expect(same.find('.field__hint').text()).toContain('Saved')
    const empty = mountField()
    await flushPromises()
    expect(empty.find('.field__hint').text()).toBe('Enter a score from 0 to 10.00')
  })

  it('is disabled when read-only and keeps the decimal keyboard', async () => {
    const wrapper = mountField({ disabled: true })
    await flushPromises()
    const input = wrapper.find('input')
    expect(input.attributes('disabled')).toBeDefined()
    expect(input.attributes('inputmode')).toBe('decimal')
  })
})
