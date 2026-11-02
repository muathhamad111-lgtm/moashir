import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseTextarea from '@/components/form/BaseTextarea.vue'

describe('BaseTextarea', () => {
  it('binds v-model on a labelled textarea', async () => {
    const wrapper = mount(BaseTextarea, {
      props: { modelValue: '', label: 'نبذة عن الجهة', name: 'about', rows: 3 },
    })
    const textarea = wrapper.find('textarea.field__control')
    expect(textarea.attributes('rows')).toBe('3')
    expect(wrapper.find('label').attributes('for')).toBe(textarea.attributes('id'))
    await textarea.setValue('تأسست الجمعية عام 2015')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['تأسست الجمعية عام 2015'])
  })

  it('shows a field error with role=alert', () => {
    const wrapper = mount(BaseTextarea, {
      props: { modelValue: '', label: 'نبذة', name: 'about', error: 'هذا الحقل مطلوب' },
    })
    expect(wrapper.find('.field__error').attributes('role')).toBe('alert')
  })
})
