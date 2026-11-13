import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useCertificatesHub } from '@/composables/useCertificatesHub'
import { certificatesService } from '@/services/certificatesService'
import { accreditedService } from '@/services/accreditedService'
import { assessmentsService } from '@/services/assessmentsService'

vi.mock('@/services/certificatesService', () => ({ certificatesService: { listCards: vi.fn() } }))
vi.mock('@/services/accreditedService', () => ({ accreditedService: { getCurrent: vi.fn() } }))
vi.mock('@/services/assessmentsService', () => ({ assessmentsService: { list: vi.fn() } }))

const CERTIFICATES = [
  { id: 'a', status: 'issued', requestId: 9 },
  { id: 'b', status: 'suspended', requestId: 8 },
  { id: 'c', status: 'superseded', requestId: 9 },
]

beforeEach(() => {
  vi.clearAllMocks()
  certificatesService.listCards.mockResolvedValue({
    data: CERTIFICATES,
    meta: { statusCounts: { issued: 1, suspended: 1, revoked: 0, superseded: 1 } },
  })
  accreditedService.getCurrent.mockResolvedValue({
    data: { active: { id: 14, number: 'ACC-2026-000014', status: 'draft' } },
  })
  assessmentsService.list.mockResolvedValue({
    data: [
      { id: 9, number: 'ACC-2025-000009', status: 'completed' },
      { id: 11, number: 'ACC-2026-000011', status: 'completed' },
    ],
  })
})

describe('useCertificatesHub', () => {
  it('groups valid and previous certificates and counts the derived issuing tab', async () => {
    const { api } = withSetup(() => useCertificatesHub())
    await flushPromises()
    expect(api.tabs.value.map((tab) => tab.count)).toEqual([2, 2, 1])
    expect(api.visible.value.map((certificate) => certificate.id)).toEqual(['a', 'b'])
    expect(api.issuing.value.map((request) => request.id)).toEqual([14, 11])
    expect(assessmentsService.list).toHaveBeenCalledWith(
      expect.objectContaining({ 'filter[path]': 'accredited', 'filter[status]': 'completed' }),
    )
  })

  it('shows the previous certificates on their tab and fails as one block', async () => {
    const { api } = withSetup(() => useCertificatesHub())
    await flushPromises()
    api.tab.value = 'previous'
    await vi.waitFor(() =>
      expect(api.visible.value.map((certificate) => certificate.id)).toEqual(['c']),
    )
    certificatesService.listCards.mockRejectedValueOnce(
      Object.assign(new Error('down'), { normalized: { message: 'down' } }),
    )
    await api.load()
    expect(api.status.value).toBe('error')
  })
})
