import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { requestsService } from '@/services/requestsService'

vi.mock('@/services/requestsService', () => ({
  requestsService: { listQueue: vi.fn(), listAssigned: vi.fn() },
}))

const QUEUE = [{ path: 'accredited', pathLabel: null, total: 2, awaitingAssignment: 1, overdue: 0 }]

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('request path counts store', () => {
  it('loads the counts with a single row and keeps them on failure', async () => {
    const store = useRequestPathCountsStore()
    requestsService.listQueue.mockResolvedValue({ data: [], meta: { pathCounts: QUEUE } })
    await store.refreshQueue()
    expect(requestsService.listQueue).toHaveBeenCalledWith({ perPage: 1 })
    expect(store.queue).toEqual(QUEUE)
    requestsService.listAssigned.mockRejectedValue(new Error('offline'))
    await store.refreshAssigned()
    expect(store.assigned).toEqual([])
  })

  it('forgets the counts of the previous account', () => {
    const store = useRequestPathCountsStore()
    store.claim(8)
    store.applyQueue(QUEUE)
    store.claim(8)
    expect(store.queue).toEqual(QUEUE)
    store.claim(10)
    expect(store.queue).toEqual([])
  })
})
