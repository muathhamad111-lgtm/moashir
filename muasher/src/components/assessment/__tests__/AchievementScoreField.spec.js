import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useForm } from 'vee-validate'
import RawScoreField from '@/components/assessment/RawScoreField.vue'
import { createTestGlobals } from '@/test-utils'

function mountField(props, initial = '') {
  let form
  const Host = defineComponent({
    setup() {
      form = useForm({ initialValues: { 'point-1': initial } })
      return () => h(RawScoreField, { name: 'point-1', label: 'Approved plan', ...props })
    },
  })
  const wrapper = mount(Host, { global: createTestGlobals({ locale: 'en' }) })
  return { wrapper, values: () => form.values }
}

describe('RawScoreField by scoring type', () => {
  it('offers achieved or not achieved as two choices and stores 1 or 0', async () => {
    const { wrapper, values } = mountField({ scoringType: 'achievement' })
    const choices = wrapper.findAll('input[type="radio"]')
    expect(choices.map((choice) => choice.element.value)).toEqual(['1', '0'])
    expect(wrapper.text()).toContain('Choose achieved or not achieved')
    await choices[0].setValue(true)
    expect(values()['point-1']).toBe('1')
  })

  it('shows a saved choice in words', () => {
    const { wrapper } = mountField(
      { scoringType: 'achievement', saved: { rawValue: '0.00', percent: '0.00' } },
      '0.00',
    )
    expect(wrapper.text()).toContain('Saved: Not achieved')
    expect(wrapper.find('input[value="0"]').element.checked).toBe(true)
  })

  it('keeps the numeric stepper with the maximum of the other types', () => {
    const relative = mountField({ scoringType: 'relative' }).wrapper
    expect(relative.find('input[type="radio"]').exists()).toBe(false)
    expect(relative.text()).toContain('100')
    expect(mountField({ scoringType: 'score' }).wrapper.text()).toContain('10')
  })
})
