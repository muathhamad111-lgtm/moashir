import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useLegalDocumentsStore } from '@/stores/legalDocuments'
import { legalService } from '@/services/legalService'
import { contentService } from '@/services/contentService'

vi.mock('@/services/legalService', () => ({ legalService: { getCurrent: vi.fn() } }))
vi.mock('@/services/contentService', () => ({ contentService: { getPage: vi.fn() } }))

beforeEach(() => {
  vi.clearAllMocks()
  setActivePinia(createPinia())
})

describe('legal documents store', () => {
  it('loads each document once and marks a missing publication', async () => {
    legalService.getCurrent.mockImplementation((kind) =>
      kind === 'terms'
        ? Promise.resolve({ data: { kind, body: { ar: [], en: [] } } })
        : Promise.reject(Object.assign(new Error('404'), { normalized: { status: 404 } })),
    )
    contentService.getPage.mockResolvedValue({ data: { sections: [] } })
    const store = useLegalDocumentsStore()
    await Promise.all([store.loadAll(), store.load('terms')])
    await store.load('terms')
    expect(legalService.getCurrent).toHaveBeenCalledTimes(2)
    expect(store.stateOf('terms')).toBe('available')
    expect(store.stateOf('privacy')).toBe('missing')
    expect(store.isAvailable('accessibility')).toBe(true)
  })

  it('keeps a failure retryable', async () => {
    legalService.getCurrent.mockRejectedValueOnce(new Error('down'))
    const store = useLegalDocumentsStore()
    await store.load('privacy')
    expect(store.stateOf('privacy')).toBe('error')
    legalService.getCurrent.mockResolvedValueOnce({ data: { kind: 'privacy' } })
    await store.load('privacy')
    expect(store.stateOf('privacy')).toBe('available')
  })
})
