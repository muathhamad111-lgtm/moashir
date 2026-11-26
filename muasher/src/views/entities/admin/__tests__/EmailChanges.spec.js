import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import EmailChanges from '@/views/entities/admin/EmailChanges.vue'
import { entityEmailChangesService } from '@/services/entityEmailChangesService'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/entityEmailChangesService', () => ({
  entityEmailChangesService: { list: vi.fn(), approve: vi.fn(), reject: vi.fn() },
}))

const ROW = {
  id: 'c-1',
  entity: { id: 7, reference: 'ENT-000007', name: { ar: 'جمعية الأمل', en: 'Al-Amal' } },
  oldEmail: 'old@alamal.example.sa',
  newEmail: 'new@alamal.example.sa',
  policy: 'requires_approval',
  status: 'awaiting-approval',
  requestedAt: '2026-11-20T08:00:00Z',
  otpVerifiedAt: '2026-11-20T08:05:00Z',
  appliedAt: null,
}

beforeEach(() => {
  vi.clearAllMocks()
  entityEmailChangesService.list.mockResolvedValue({
    data: [ROW],
    meta: { pagination: { page: 1, perPage: 10, total: 1, lastPage: 1 } },
  })
})

describe('EmailChanges optional columns', () => {
  it('shows the entity reference and the verification date once chosen, and an empty applied date', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    setActivePinia(globals.pinia)
    const wrapper = mount(EmailChanges, { global: { ...globals, stubs: { teleport: true } } })
    await flushPromises()
    useTableColumnsStore().choices = {
      'entities-email-changes': ['newEmail', 'entityReference', 'otpVerifiedAt', 'appliedAt'],
    }
    await flushPromises()
    const cells = wrapper.findAll('tbody td').map((cell) => cell.text())
    expect(cells[2]).toBe('ENT-000007')
    expect(cells[3]).toContain('2026-11-20')
    expect(cells[4]).toBe('')
    wrapper.unmount()
  })
})
