import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AttachmentsEditor from '@/components/criteria/AttachmentsEditor.vue'
import { createTestGlobals } from '@/test-utils'

function mountEditor(modelValue = []) {
  return mount(AttachmentsEditor, {
    props: { modelValue },
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
    expect(emitted[0]).toEqual({ key: null, name: { ar: '', en: '' }, required: false })
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
})
