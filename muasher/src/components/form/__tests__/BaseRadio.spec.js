import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseRadio from '@/components/form/BaseRadio.vue'

const options = [
  { value: 'association', label: 'جمعية خيرية' },
  { value: 'foundation', label: 'مؤسسة أهلية' },
]

describe('BaseRadio', () => {
  it('renders a fieldset with legend and one labelled radio per option', () => {
    const wrapper = mount(BaseRadio, {
      props: { modelValue: null, label: 'نوع الجهة', name: 'type', options },
    })
    expect(wrapper.find('fieldset').exists()).toBe(true)
    expect(wrapper.find('legend').text()).toBe('نوع الجهة')
    const radios = wrapper.findAll('input[type="radio"]')
    expect(radios).toHaveLength(2)
    expect(radios[0].attributes('name')).toBe('type')
  })

  it('checks the selected option and emits on change', async () => {
    const wrapper = mount(BaseRadio, {
      props: { modelValue: 'association', label: 'نوع الجهة', name: 'type', options },
    })
    const radios = wrapper.findAll('input[type="radio"]')
    expect(radios[0].element.checked).toBe(true)
    await radios[1].setValue(true)
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['foundation'])
  })
})
