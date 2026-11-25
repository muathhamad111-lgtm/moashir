import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import EntityDocumentsCard from '@/components/entities/admin/EntityDocumentsCard.vue'
import { entitiesService } from '@/services/entitiesService'
import { filesService } from '@/services/filesService'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

vi.mock('@/services/entitiesService', () => ({
  entitiesService: { requestDocumentDownload: vi.fn() },
}))
vi.mock('@/services/filesService', () => ({ filesService: { fetchSignedFile: vi.fn() } }))
vi.mock('@/utils/download', () => ({ downloadBlob: vi.fn() }))

const LINK = { url: 'https://moashir.test/api/v1/files/f-1/download', fileName: 'license.pdf' }

const ENTITY = {
  id: 5001,
  documents: {
    licenseCertificate: {
      id: 'f-1',
      name: 'license.pdf',
      mimeType: 'application/pdf',
      sizeBytes: 204800,
      uploadedAt: '2026-06-28T06:15:00Z',
    },
    authorizationLetter: null,
    vatCertificate: null,
  },
}

async function mountCard() {
  const wrapper = mount(EntityDocumentsCard, {
    global: createTestGlobals({ locale: 'ar' }),
    props: { entity: ENTITY },
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

function rowOf(wrapper, key) {
  return wrapper.find(`[data-document="${key}"]`)
}

beforeEach(() => {
  vi.clearAllMocks()
  stubMatchMedia({ width: 1440 })
  URL.createObjectURL = vi.fn(() => 'blob:preview')
  URL.revokeObjectURL = vi.fn()
  entitiesService.requestDocumentDownload.mockResolvedValue({ data: LINK })
  filesService.fetchSignedFile.mockResolvedValue({
    blob: new Blob(['%PDF-1.4']),
    fileName: 'license.pdf',
  })
})

describe('EntityDocumentsCard', () => {
  it('lists the three documents and marks the missing ones «غير مرفقة»', async () => {
    const wrapper = await mountCard()
    expect(wrapper.text()).toContain('وثائق الجهة')
    expect(rowOf(wrapper, 'licenseCertificate').text()).toContain('شهادة الترخيص')
    expect(rowOf(wrapper, 'licenseCertificate').text()).toContain('license.pdf')
    expect(rowOf(wrapper, 'authorizationLetter').text()).toContain('غير مرفقة')
    expect(rowOf(wrapper, 'vatCertificate').text()).toContain('غير مرفقة')
    expect(rowOf(wrapper, 'authorizationLetter').findAll('button')).toHaveLength(0)
  })

  it('previews a document inline through the staff download link', async () => {
    const wrapper = await mountCard()
    const preview = rowOf(wrapper, 'licenseCertificate')
      .findAll('button')
      .find((button) => button.text() === 'معاينة')
    await preview.trigger('click')
    await flushPromises()
    expect(entitiesService.requestDocumentDownload).toHaveBeenCalledWith(5001, 'licenseCertificate')
    expect(document.body.querySelector('iframe.document-viewer__pdf')?.getAttribute('src')).toBe(
      'blob:preview',
    )
    wrapper.unmount()
  })

  it('downloads a document', async () => {
    const wrapper = await mountCard()
    const download = rowOf(wrapper, 'licenseCertificate')
      .findAll('button')
      .find((button) => button.text() === 'تنزيل')
    await download.trigger('click')
    await flushPromises()
    expect(filesService.fetchSignedFile).toHaveBeenCalledWith(LINK)
    wrapper.unmount()
  })
})
