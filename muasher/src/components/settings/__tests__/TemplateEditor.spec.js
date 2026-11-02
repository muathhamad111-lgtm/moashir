import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import TemplateEditor from '@/components/settings/TemplateEditor.vue'
import { createTestGlobals } from '@/test-utils'

const placeholders = [
  { token: '{OTP}', label: { ar: 'رمز التحقق', en: 'Verification code' } },
  { token: '{اسم الجهة}', label: { ar: 'اسم الجهة', en: 'Entity name' } },
]

function mountEditor(props = {}) {
  return mount(TemplateEditor, {
    props: {
      name: 'tpl',
      modelValue: { ar: 'مرحبا', en: 'Hello' },
      placeholders,
      ...props,
    },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('TemplateEditor', () => {
  it('renders an Arabic and an English field with the current values', () => {
    const wrapper = mountEditor()
    const arField = wrapper.find('#tpl-ar')
    const enField = wrapper.find('#tpl-en')
    expect(arField.element.value).toBe('مرحبا')
    expect(arField.attributes('dir')).toBe('rtl')
    expect(enField.element.value).toBe('Hello')
    expect(enField.attributes('dir')).toBe('ltr')
  })

  it('emits an updated { ar, en } object when a field changes', async () => {
    const wrapper = mountEditor()
    await wrapper.find('#tpl-en').setValue('Hello world')
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toEqual({
      ar: 'مرحبا',
      en: 'Hello world',
    })
  })

  it('renders a placeholder chip per token and inserts it into the active field', async () => {
    const wrapper = mountEditor({ modelValue: { ar: '', en: '' } })
    const chips = wrapper.findAll('.template-editor__chip')
    expect(chips).toHaveLength(2)
    await wrapper.find('#tpl-ar').trigger('focus')
    await chips[0].trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toEqual({ ar: '{OTP}', en: '' })
  })

  it('disables editing and the palette when disabled', () => {
    const wrapper = mountEditor({ disabled: true })
    expect(wrapper.find('fieldset').attributes('disabled')).toBeDefined()
  })
})
