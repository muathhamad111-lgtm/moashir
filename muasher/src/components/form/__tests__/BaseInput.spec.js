import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseInput from '@/components/form/BaseInput.vue'

describe('BaseInput', () => {
  it('binds v-model and keeps the label associated', async () => {
    const wrapper = mount(BaseInput, {
      props: { modelValue: '', label: 'اسم الجهة', name: 'entityName' },
    })
    const input = wrapper.find('input.field__control')
    expect(wrapper.find('label').attributes('for')).toBe(input.attributes('id'))
    await input.setValue('جمعية البر')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['جمعية البر'])
  })

  it('accepts English input as well', async () => {
    const wrapper = mount(BaseInput, {
      props: { modelValue: '', label: 'Name', name: 'name' },
    })
    await wrapper.find('input').setValue('Al-Birr Association')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['Al-Birr Association'])
  })

  it('wires error to aria-invalid and aria-describedby', () => {
    const wrapper = mount(BaseInput, {
      props: { modelValue: '', label: 'البريد', name: 'email', error: 'أدخل بريدًا صحيحًا' },
    })
    const input = wrapper.find('input')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toBe(wrapper.find('.field__error').attributes('id'))
  })

  it('disables the control when disabled', () => {
    const wrapper = mount(BaseInput, {
      props: { modelValue: '', label: 'اسم', name: 'n', disabled: true },
    })
    expect(wrapper.find('input').attributes('disabled')).toBeDefined()
  })

  it('floats the label when it holds a value and keeps the label as the placeholder at rest', () => {
    const empty = mount(BaseInput, {
      props: { modelValue: '', label: 'اسم الجهة', name: 'entityName' },
    })
    expect(empty.find('.field').classes()).not.toContain('field--floated')
    expect(empty.find('input').attributes('placeholder')).toBe(' ')

    const filled = mount(BaseInput, {
      props: { modelValue: 'جمعية البر', label: 'اسم الجهة', name: 'entityName' },
    })
    expect(filled.find('.field').classes()).toContain('field--floated')
  })

  it('floats the label while the control is focused', async () => {
    const wrapper = mount(BaseInput, {
      props: { modelValue: '', label: 'اسم الجهة', name: 'entityName' },
    })
    await wrapper.find('input').trigger('focusin')
    expect(wrapper.find('.field').classes()).toContain('field--floated')
    expect(wrapper.find('.field').classes()).toContain('field--focused')
  })
})
