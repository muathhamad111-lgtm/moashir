import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FormField from '@/components/form/FormField.vue'

describe('FormField', () => {
  it('associates the label with the slotted control id', () => {
    const wrapper = mount(FormField, {
      props: { label: 'اسم الجهة', name: 'entityName' },
      slots: { default: '<input :id="params.id" />' },
    })
    const label = wrapper.find('label.field__label')
    const input = wrapper.find('input')
    expect(label.text()).toBe('اسم الجهة')
    expect(label.attributes('for')).toBe(input.attributes('id'))
  })

  it('renders the error with role=alert and the error modifier class', () => {
    const wrapper = mount(FormField, {
      props: { label: 'البريد', name: 'email', error: 'هذا الحقل مطلوب' },
      slots: { default: '<input />' },
    })
    expect(wrapper.classes()).toContain('field--error')
    const error = wrapper.find('.field__error')
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toBe('هذا الحقل مطلوب')
  })

  it('shows the hint only when there is no error and applies state classes', () => {
    const wrapper = mount(FormField, {
      props: { label: 'الوزن', name: 'weight', hint: 'من 0 إلى 100', required: true, disabled: true },
      slots: { default: '<input />' },
    })
    expect(wrapper.find('.field__hint').text()).toBe('من 0 إلى 100')
    expect(wrapper.classes()).toContain('field--required')
    expect(wrapper.classes()).toContain('field--disabled')
  })

  it('floats the label when the field is filled', () => {
    const wrapper = mount(FormField, {
      props: { label: 'اسم الجهة', name: 'entityName', filled: true },
      slots: { default: '<input />' },
    })
    expect(wrapper.classes()).toContain('field--floated')
    expect(wrapper.classes()).not.toContain('field--focused')
  })

  it('does not float the label at rest when empty and unfocused', () => {
    const wrapper = mount(FormField, {
      props: { label: 'اسم الجهة', name: 'entityName' },
      slots: { default: '<input />' },
    })
    expect(wrapper.classes()).not.toContain('field--floated')
    expect(wrapper.classes()).not.toContain('field--focused')
  })

  it('floats and marks focus while a control inside is focused, then settles on blur', async () => {
    const wrapper = mount(FormField, {
      props: { label: 'البريد', name: 'email' },
      slots: { default: '<input />' },
    })
    await wrapper.find('input').trigger('focusin')
    expect(wrapper.classes()).toContain('field--focused')
    expect(wrapper.classes()).toContain('field--floated')
    await wrapper.find('input').trigger('focusout')
    expect(wrapper.classes()).not.toContain('field--focused')
    expect(wrapper.classes()).not.toContain('field--floated')
  })

  it('stays floated on blur while a value is present', async () => {
    const wrapper = mount(FormField, {
      props: { label: 'البريد', name: 'email', filled: true },
      slots: { default: '<input />' },
    })
    await wrapper.find('input').trigger('focusin')
    await wrapper.find('input').trigger('focusout')
    expect(wrapper.classes()).not.toContain('field--focused')
    expect(wrapper.classes()).toContain('field--floated')
  })
})
