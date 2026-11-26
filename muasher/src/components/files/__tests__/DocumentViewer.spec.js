import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import DocumentViewer from '@/components/files/DocumentViewer.vue'
import { filesService } from '@/services/filesService'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

vi.mock('@/services/filesService', () => ({ filesService: { fetchSignedFile: vi.fn() } }))

const LINK = { url: 'https://moashir.test/api/v1/files/f-1/download', fileName: 'proof.pdf' }

function mountViewer(file, label = 'Bank transfer receipt') {
  const requestLink = vi.fn().mockResolvedValue({ data: LINK })
  const wrapper = mount(DocumentViewer, {
    global: createTestGlobals({ locale: 'en' }),
    props: { file, label, requestLink },
  })
  return { wrapper, requestLink }
}

beforeEach(() => {
  vi.clearAllMocks()
  URL.createObjectURL = vi.fn(() => 'blob:preview')
  URL.revokeObjectURL = vi.fn()
  filesService.fetchSignedFile.mockResolvedValue({
    blob: new Blob(['%PDF-1.4']),
    fileName: 'proof.pdf',
  })
})

afterEach(() => vi.unstubAllGlobals())

describe('DocumentViewer', () => {
  it('previews a PDF inline on a large screen, named after its label, and notes the audit', async () => {
    stubMatchMedia({ width: 1440 })
    const { wrapper, requestLink } = mountViewer(
      { name: 'license.pdf', mimeType: 'application/pdf', sizeBytes: 2048 },
      'Licence certificate',
    )
    await flushPromises()
    expect(requestLink).toHaveBeenCalledOnce()
    expect(filesService.fetchSignedFile).toHaveBeenCalledWith(LINK, { intent: 'view' })
    const frame = wrapper.find('iframe.document-viewer__pdf')
    expect(frame.attributes('src')).toBe('blob:preview')
    expect(frame.attributes('title')).toBe('Preview of Licence certificate license.pdf')
    expect(wrapper.find('.document-viewer').attributes('aria-label')).toBe('Licence certificate')
    expect(wrapper.text()).toContain('Scanned')
    expect(wrapper.text()).toContain('Every view and download of this file is logged.')
  })

  it('keeps the finance proof flow: an image proof with zoom, and an unscanned proof not fetched', async () => {
    stubMatchMedia({ width: 1440 })
    const image = mountViewer({
      name: 'receipt.png',
      mimeType: 'image/png',
      sizeBytes: 4096,
      scanStatus: 'clean',
    })
    await flushPromises()
    expect(image.wrapper.find('img.document-viewer__image').attributes('alt')).toBe(
      'Bank transfer receipt receipt.png',
    )
    await image.wrapper.find('[aria-label="Zoom in"]').trigger('click')
    expect(image.wrapper.find('.document-viewer__zoom-value').text()).not.toBe('100%')

    const pending = mountViewer({
      name: 'receipt.pdf',
      mimeType: 'application/pdf',
      sizeBytes: 1,
      scanStatus: 'pending',
    })
    await flushPromises()
    expect(pending.requestLink).not.toHaveBeenCalled()
    expect(pending.wrapper.text()).toContain(
      'The file cannot be shown before its scan is complete.',
    )
  })

  it('asks to open a PDF on a small screen instead of embedding it', async () => {
    stubMatchMedia({ width: 390 })
    const { wrapper, requestLink } = mountViewer({
      name: 'letter.pdf',
      mimeType: 'application/pdf',
      sizeBytes: 10,
    })
    await flushPromises()
    expect(requestLink).not.toHaveBeenCalled()
    expect(wrapper.find('iframe').exists()).toBe(false)
    expect(wrapper.text()).toContain('PDF files are shown inline on large screens.')
  })
})
