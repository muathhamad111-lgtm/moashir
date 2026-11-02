import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'

describe('BaseCheckbox', () => {
  it('binds v-model to the labelled checkbox', async () => {
    const wrapper = mount(BaseCheckbox, {
      props: { modelValue: false, label: 'أوافق على الشروط', name: 'agree' },
    })
    const input = wrapper.find('input[type="checkbox"]')
    expect(wrapper.find('label').attributes('for')).toBe(input.attributes('id'))
    await input.setValue(true)
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([true])
  })

  it('renders the error and links it via aria-describedby', () => {
    const wrapper = mount(BaseCheckbox, {
      props: { modelValue: false, label: 'أوافق', name: 'agree', error: 'يجب الموافقة' },
    })
    const input = wrapper.find('input')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toBe(wrapper.find('.field__error').attributes('id'))
  })

  it('marks the wrapper disabled', () => {
    const wrapper = mount(BaseCheckbox, {
      props: { modelValue: false, label: 'أوافق', name: 'agree', disabled: true },
    })
    expect(wrapper.find('.checkbox').classes()).toContain('is-disabled')
    expect(wrapper.find('input').attributes('disabled')).toBeDefined()
  })
})
