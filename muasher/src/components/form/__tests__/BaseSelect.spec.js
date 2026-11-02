import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseSelect from '@/components/form/BaseSelect.vue'

const options = [
  { value: 'association', label: 'جمعية خيرية' },
  { value: 'foundation', label: 'مؤسسة أهلية' },
]

describe('BaseSelect', () => {
  it('renders the placeholder as a disabled option and the options list', () => {
    const wrapper = mount(BaseSelect, {
      props: { modelValue: null, label: 'نوع الجهة', name: 'type', options, placeholder: 'اختر…' },
    })
    const optionEls = wrapper.findAll('option')
    expect(optionEls).toHaveLength(3)
    expect(optionEls[0].attributes('disabled')).toBeDefined()
    expect(wrapper.find('label').attributes('for')).toBe(wrapper.find('select').attributes('id'))
  })

  it('emits update:modelValue on change', async () => {
    const wrapper = mount(BaseSelect, {
      props: { modelValue: null, label: 'نوع الجهة', name: 'type', options },
    })
    await wrapper.find('select').setValue('foundation')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['foundation'])
  })
})
