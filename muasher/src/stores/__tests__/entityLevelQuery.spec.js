import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useEntityLevelStore } from '@/stores/entityLevel'
import { certificatesService } from '@/services/certificatesService'

vi.mock('@/services/certificatesService', () => ({ certificatesService: { listCards: vi.fn() } }))
vi.mock('@/services/accreditedService', () => ({
  accreditedService: { getCurrent: vi.fn().mockResolvedValue({ data: null }) },
}))

beforeEach(() => {
  setActivePinia(createPinia())
  certificatesService.listCards.mockResolvedValue({ data: [] })
})

describe('entity level store', () => {
  it('reads the level from accredited certificates only, never from an award certificate', async () => {
    await useEntityLevelStore().load()
    expect(certificatesService.listCards).toHaveBeenCalledWith(
      expect.objectContaining({ 'filter[kind]': 'accredited', perPage: 1 }),
    )
  })
})
