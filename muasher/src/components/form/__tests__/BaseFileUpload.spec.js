import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import { createTestGlobals } from '@/test-utils'

const files = [
  { id: 1, name: 'الخطة-الاستراتيجية.pdf', status: 'done' },
  { id: 2, name: 'governance-report.xlsx', status: 'uploading', progress: 40 },
  { id: 3, name: 'محضر-الاجتماع.docx', status: 'failed' },
]

function factory(props = {}) {
  return mount(BaseFileUpload, {
    props: { files, label: 'الشواهد', name: 'evidence', ...props },
    global: createTestGlobals(),
  })
}

describe('BaseFileUpload', () => {
  it('lists files with per-status text (uploaded / uploading / failed)', () => {
    const wrapper = factory()
    const items = wrapper.findAll('.file-upload__item')
    expect(items).toHaveLength(3)
    expect(items[0].find('.file-upload__status').text()).toBe('تم الرفع')
    expect(items[1].find('.file-upload__status').text()).toContain('40')
    expect(items[2].find('.file-upload__status').text()).toBe('فشل الرفع')
    expect(items[2].classes()).toContain('is-failed')
  })

  it('shows a progress bar only for uploading files', () => {
    const wrapper = factory()
    const progressBars = wrapper.findAll('progress')
    expect(progressBars).toHaveLength(1)
    expect(progressBars[0].attributes('value')).toBe('40')
  })

  it('emits remove and retry with the file id', async () => {
    const wrapper = factory()
    await wrapper.find('[aria-label="إزالة الخطة-الاستراتيجية.pdf"]').trigger('click')
    expect(wrapper.emitted('remove')[0]).toEqual([1])
    await wrapper.find('[aria-label="إعادة رفع محضر-الاجتماع.docx"]').trigger('click')
    expect(wrapper.emitted('retry')[0]).toEqual([3])
  })

  it('emits select with the chosen files', async () => {
    const wrapper = factory()
    const input = wrapper.find('input[type="file"]')
    const file = new File(['data'], 'evidence.pdf', { type: 'application/pdf' })
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    expect(wrapper.emitted('select')[0][0]).toEqual([file])
  })
})
