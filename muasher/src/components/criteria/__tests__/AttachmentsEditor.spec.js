import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AttachmentsEditor from '@/components/criteria/AttachmentsEditor.vue'
import { createTestGlobals } from '@/test-utils'

function mountEditor(modelValue = [], errors = {}) {
  return mount(AttachmentsEditor, {
    props: { modelValue, errors },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('AttachmentsEditor', () => {
  it('shows an empty message with no attachments', () => {
    const wrapper = mountEditor([])
    expect(wrapper.find('.attachments-editor__empty').exists()).toBe(true)
  })

  it('emits a new empty bilingual attachment when adding', async () => {
    const wrapper = mountEditor([])
    await wrapper.find('.attachments-editor button').trigger('click')
    const emitted = wrapper.emitted('update:modelValue')[0][0]
    expect(emitted).toHaveLength(1)
    expect(emitted[0]).toEqual({
      key: null,
      name: { ar: '', en: '' },
      guidance: { ar: '', en: '' },
      required: false,
      allowedTypes: [],
      maxFiles: '',
    })
  })

  it('updates the Arabic name of a row without losing the English name', async () => {
    const wrapper = mountEditor([{ name: { ar: '', en: 'Plan' }, required: false }])
    const arabicInput = wrapper.findAll('.field__control')[0]
    await arabicInput.setValue('الخطة')
    const emitted = wrapper.emitted('update:modelValue').at(-1)[0]
    expect(emitted[0].name).toEqual({ ar: 'الخطة', en: 'Plan' })
  })

  it('toggles the required flag', async () => {
    const wrapper = mountEditor([{ name: { ar: 'خطة', en: 'Plan' }, required: false }])
    await wrapper.find('.checkbox__input').setValue(true)
    const emitted = wrapper.emitted('update:modelValue').at(-1)[0]
    expect(emitted[0].required).toBe(true)
  })

  it('removes an attachment', async () => {
    const wrapper = mountEditor([
      { name: { ar: 'أ', en: 'A' }, required: true },
      { name: { ar: 'ب', en: 'B' }, required: false },
    ])
    const removeButtons = wrapper.findAll('.attachments-editor__controls button')
    await removeButtons.at(-1).trigger('click')
    const emitted = wrapper.emitted('update:modelValue').at(-1)[0]
    expect(emitted).toHaveLength(1)
    expect(emitted[0].name.en).toBe('A')
  })

  it('keeps each remaining row element when an earlier row is removed', async () => {
    const first = { name: { ar: 'أ', en: 'A' }, required: true }
    const second = { name: { ar: 'ب', en: 'B' }, required: false }
    const wrapper = mountEditor([first, second])
    const secondRow = wrapper.findAll('.attachments-editor__row')[1].element
    await wrapper.setProps({ modelValue: [second] })
    expect(wrapper.find('.attachments-editor__row').element).toBe(secondRow)
  })

  it('keeps the row element while its name is edited', async () => {
    const wrapper = mountEditor([{ name: { ar: '', en: 'Plan' }, required: false }])
    const row = wrapper.find('.attachments-editor__row').element
    await wrapper.findAll('.field__control')[0].setValue('الخطة')
    await wrapper.setProps({ modelValue: wrapper.emitted('update:modelValue').at(-1)[0] })
    expect(wrapper.find('.attachments-editor__row').element).toBe(row)
  })

  it('sets the guidance, the file types and the file limit of a row', async () => {
    const wrapper = mountEditor([
      {
        key: 'plan',
        name: { ar: 'الخطة', en: 'Plan' },
        guidance: { ar: '', en: '' },
        required: true,
        allowedTypes: ['pdf'],
        maxFiles: '',
      },
    ])
    expect(
      wrapper.find('input[value="pdf"]').element.parentElement.querySelector('.checkbox__check'),
    ).not.toBeNull()
    expect(
      wrapper.find('input[value="docx"]').element.parentElement.querySelector('.checkbox__check'),
    ).toBeNull()
    await wrapper.find('input[value="docx"]').setValue(true)
    expect(wrapper.emitted('update:modelValue').at(-1)[0][0].allowedTypes).toEqual(['pdf', 'docx'])
    await wrapper.find('input[name="attachmentMaxFiles0"]').setValue('3a')
    expect(wrapper.emitted('update:modelValue').at(-1)[0][0].maxFiles).toBe('3')
    await wrapper.find('textarea[name="attachmentGuidanceEn0"]').setValue('Signed plan')
    expect(wrapper.emitted('update:modelValue').at(-1)[0][0].guidance).toEqual({
      ar: '',
      en: 'Signed plan',
    })
  })

  it('shows the server errors of a row at their fields and opens its details', () => {
    const wrapper = mountEditor(
      [{ name: { ar: 'خ', en: 'Plan' }, guidance: { ar: '', en: 'Signed' }, required: true }],
      {
        'attachments.0.name.ar': 'Enter at least two letters.',
        'attachments.0.guidance.ar': 'Enter the Arabic guidance.',
      },
    )
    const arabicName = wrapper.find('input[dir="rtl"]')
    expect(arabicName.attributes('aria-invalid')).toBe('true')
    expect(wrapper.find(`#${arabicName.attributes('aria-describedby')}`).text()).toBe(
      'Enter at least two letters.',
    )
    expect(wrapper.find('details').attributes('open')).toBeDefined()
    expect(wrapper.text()).toContain('Enter the Arabic guidance.')
  })
})
