import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EvidenceUploader from '@/components/assessment/EvidenceUploader.vue'
import { createTestGlobals } from '@/test-utils'

const attachments = [
  { key: 'a0', name: 'Approved governance charter', required: true },
  { key: 'a1', name: 'Board meeting minutes', required: false },
]

function mountUploader(props = {}) {
  return mount(EvidenceUploader, {
    props: { attachments, files: [], uploading: {}, ...props },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('EvidenceUploader', () => {
  it('renders a slot per attachment with a required/optional marker (text, not color alone)', () => {
    const wrapper = mountUploader()
    const slots = wrapper.findAll('.evidence-uploader__slot')
    expect(slots).toHaveLength(2)
    const markers = wrapper.findAll('.evidence-uploader__req')
    expect(markers[0].text()).toBe('Required')
    expect(markers[1].text()).toBe('Optional')
  })

  it('flags the summary as incomplete when a required attachment has no file', () => {
    const wrapper = mountUploader({ files: [] })
    const summary = wrapper.find('.evidence-uploader__summary')
    expect(summary.text()).toContain('1')
    expect(wrapper.find('.evidence-uploader__slot.is-missing').exists()).toBe(true)
  })

  it('marks the summary complete once every required slot is satisfied', () => {
    const wrapper = mountUploader({
      files: [{ id: 'e1', attachmentKey: 'a0', fileName: 'charter.pdf', sizeKb: 120 }],
    })
    const summary = wrapper.find('.evidence-uploader__summary')
    expect(summary.text()).toContain('All required attachments have been uploaded.')
    expect(wrapper.find('.evidence-uploader__slot.is-missing').exists()).toBe(false)
  })

  it('lists uploaded files under their slot with a remove control', () => {
    const wrapper = mountUploader({
      files: [{ id: 'e1', attachmentKey: 'a0', fileName: 'charter.pdf', sizeKb: 120 }],
    })
    const file = wrapper.find('.evidence-uploader__file')
    expect(file.text()).toContain('charter.pdf')
    expect(wrapper.find('[aria-label="Remove charter.pdf"]').exists()).toBe(true)
  })

  it('emits remove with the file id', async () => {
    const wrapper = mountUploader({
      files: [{ id: 'e1', attachmentKey: 'a0', fileName: 'charter.pdf', sizeKb: 120 }],
    })
    await wrapper.find('[aria-label="Remove charter.pdf"]').trigger('click')
    expect(wrapper.emitted('remove').at(-1)).toEqual(['e1'])
  })

  it('shows a busy add button while a slot is uploading', () => {
    const wrapper = mountUploader({ uploading: { a0: true } })
    const addButtons = wrapper.findAll('.evidence-uploader__add')
    expect(addButtons[0].attributes('disabled')).toBeDefined()
    expect(addButtons[0].find('.fa-spinner').exists()).toBe(true)
  })

  it('disables all controls when disabled', () => {
    const wrapper = mountUploader({ disabled: true })
    wrapper.findAll('.evidence-uploader__add').forEach((btn) => {
      expect(btn.attributes('disabled')).toBeDefined()
    })
  })

  it('renders a hidden accessible file input labelled per slot', () => {
    const wrapper = mountUploader()
    const input = wrapper.find('input[type="file"]')
    expect(input.attributes('aria-label')).toContain('Approved governance charter')
    expect(input.attributes('hidden')).toBeDefined()
  })
})
